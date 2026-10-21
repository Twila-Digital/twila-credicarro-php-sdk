<?php

declare(strict_types=1);

namespace Twila\Credicarro\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Twila\Credicarro\Config\ClientOptions;
use Twila\Credicarro\Config\Environment;
use Twila\Credicarro\Config\ResilienceOptions;
use Twila\Credicarro\CredicarroClient;
use Twila\Credicarro\Customers\CreateCustomerRequest;
use Twila\Credicarro\Customers\CustomerAddress;
use Twila\Credicarro\Customers\OccupationType;
use Twila\Credicarro\Customers\ProfessionalData;
use Twila\Credicarro\Customers\UpsertCustomerRequest;
use Twila\Credicarro\Errors\CredicarroApiException;
use Twila\Credicarro\Errors\CredicarroAuthenticationException;
use Twila\Credicarro\Errors\CredicarroRateLimitException;
use Twila\Credicarro\Errors\CredicarroTimeoutException;
use Twila\Credicarro\Errors\CredicarroValidationException;
use Twila\Credicarro\Internal\Auth\TokenApiClient;
use Twila\Credicarro\Internal\Masking\Mask;
use Twila\Credicarro\Internal\Resilience\ResiliencePipeline;
use Twila\Credicarro\Internal\Resilience\RetryAfter;
use Twila\Credicarro\Proposals\CreateProposalRequest;
use Twila\Credicarro\Proposals\DepositAccount;
use Twila\Credicarro\Proposals\DepositAccountType;
use Twila\Credicarro\Proposals\DocumentFile;
use Twila\Credicarro\Proposals\DocumentType;
use Twila\Credicarro\Proposals\ProposalDocumentUpload;
use Twila\Credicarro\Proposals\ProposalParticipant;
use Twila\Credicarro\Webhooks\CreateWebhookRequest;
use Twila\Credicarro\Webhooks\CreateWebhookResult;
use Twila\Credicarro\Webhooks\CredicarroWebhookEvent;
use Twila\Credicarro\Webhooks\WebhookAuthenticationType;
use Twila\Credicarro\Webhooks\WebhookType;

/**
 * Testes das correções do ciclo 1 (circuit breaker, segredos/PII, orçamento, Retry-After, redirects, token, cache).
 */
final class HardeningTest extends TestCase
{
    private const SECRET = 'SEGREDO-SUPER-LONGO-123';
    private const UUID = TestClientFactory::UUID;
    private const PAGE = [
        'itens' => [],
        'pagina' => ['tem_proximo' => false, 'tem_anterior' => false, 'numero' => 1, 'tamanho' => 10, 'total' => 0],
    ];

    // ---- E1: circuit breaker -------------------------------------------------------------------

    public function testHalfOpenTrialEndedByNonNetworkExceptionDoesNotLockTheCircuit(): void
    {
        $options = new ResilienceOptions(5000, 1000, 60000, 1, 1, 0.5, 30000, 2, 150);
        $pipeline = new ResiliencePipeline($options);
        $ok = function () {
            return new \Twila\Credicarro\Internal\Http\ApiResponse(503, [], []);
        };

        $pipeline->execute(false, $ok);
        $pipeline->execute(false, $ok); // abre o circuito
        usleep(200000);

        try {
            $pipeline->execute(false, function () {
                throw new \DomainException('401 persistente / erro do token');
            });
            self::fail('Esperava DomainException.');
        } catch (\DomainException $e) {
            // a tentativa de meia-abertura terminou sem veredito
        }

        $response = $pipeline->execute(false, function () {
            return new \Twila\Credicarro\Internal\Http\ApiResponse(200, [], []);
        });

        self::assertSame(200, $response->statusCode);
    }

    public function testCircuitRecoversAfterPersistent401InHalfOpenThroughTheClient(): void
    {
        $options = new ResilienceOptions(5000, 1000, 60000, 1, 1, 0.5, 30000, 2, 200);
        $h = TestClientFactory::harness([
            new Response(503),
            new Response(503),
        ], $options);

        for ($i = 0; $i < 2; $i++) {
            try {
                $h->client->webhooks->list();
            } catch (CredicarroApiException $e) {
                self::assertSame(503, $e->getStatusCode());
            }
        }

        usleep(250000);
        $h->mock->append(
            new Response(401),
            TestClientFactory::tokenResponse('t2'),
            new Response(401),
            TestClientFactory::json(200, self::PAGE)
        );

        try {
            $h->client->webhooks->list();
            self::fail('Esperava CredicarroAuthenticationException.');
        } catch (CredicarroAuthenticationException $e) {
            self::assertSame(401, $e->getStatusCode());
        }

        // O circuito não pode ter ficado preso na meia-abertura.
        $page = $h->client->webhooks->list();
        self::assertSame(0, $page->totalCount);
    }

    // ---- Retry-After e orçamento -----------------------------------------------------------------

    public function testRetryAfterParsing(): void
    {
        $now = 1700000000;

        self::assertSame(5.0, RetryAfter::seconds('5'));
        self::assertSame(1.5, RetryAfter::seconds(' 1.5 '));
        self::assertSame(30.0, RetryAfter::seconds(gmdate('D, d M Y H:i:s', $now + 30) . ' GMT', $now));
        self::assertNull(RetryAfter::seconds(null));
        self::assertNull(RetryAfter::seconds(''));
        self::assertNull(RetryAfter::seconds('abc'));
        self::assertNull(RetryAfter::seconds('0'));
        self::assertNull(RetryAfter::seconds('-3'));
        self::assertNull(RetryAfter::seconds(gmdate('D, d M Y H:i:s', $now - 60) . ' GMT', $now));
        self::assertSame(86400.0, RetryAfter::seconds('99999999999'));
        self::assertSame(2500, RetryAfter::milliseconds('2.5'));
        self::assertSame(86400000, RetryAfter::milliseconds('1e12'));
    }

    public function testRetryAfterBeyondTheRemainingBudgetDoesNotSleep(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(429, ['tipo' => 'RateLimit'], ['Retry-After' => '30']),
            TestClientFactory::json(200, self::PAGE),
        ], new ResilienceOptions(500, 500, 60000, 3, 1));

        $started = microtime(true);
        try {
            $h->client->webhooks->list();
            self::fail('Esperava CredicarroRateLimitException.');
        } catch (CredicarroRateLimitException $e) {
            self::assertSame(30000, $e->getRetryAfterMs());
        }

        self::assertLessThan(0.4, microtime(true) - $started);
        self::assertCount(1, $h->apiRequests());
    }

    public function testNetworkBackoffNeverOutlastsTheBudget(): void
    {
        $options = new ResilienceOptions(300, 300, 60000, 5, 5000);
        $h = TestClientFactory::harness([], $options);
        $request = new Request('GET', 'https://x.test');
        $h->mock->append(
            new ConnectException('boom', $request),
            new ConnectException('boom', $request),
            new ConnectException('boom', $request)
        );

        $started = microtime(true);
        try {
            $h->client->webhooks->list();
            self::fail('Esperava CredicarroTimeoutException.');
        } catch (CredicarroTimeoutException $e) {
            // ok
        }

        self::assertLessThan(1.5, microtime(true) - $started);
    }

    public function testDocumentUploadHasAWiderBudgetThanOtherCalls(): void
    {
        // total 500 ms para chamadas comuns; upload: 600 ms por tentativa x 3 = 1800 ms de orçamento.
        $options = new ResilienceOptions(500, 500, 600, 3, 1);
        $upload = new ProposalDocumentUpload(
            ProposalParticipant::BUYER,
            DocumentType::CNH,
            DocumentFile::fromBytes('%PDF-1.7 x', 'cnh.pdf')
        );

        // Chamada comum: Retry-After de 1 s não cabe nos 500 ms -> falha na hora.
        $common = TestClientFactory::harness([
            TestClientFactory::json(503, [], ['Retry-After' => '1']),
            TestClientFactory::json(200, self::PAGE),
        ], $options);
        try {
            $common->client->webhooks->list();
            self::fail('Esperava CredicarroApiException.');
        } catch (CredicarroApiException $e) {
            self::assertSame(503, $e->getStatusCode());
        }
        self::assertCount(1, $common->apiRequests());

        // Upload: o mesmo Retry-After cabe no orçamento de 1800 ms -> espera e repete com a mesma chave.
        $h = TestClientFactory::harness([
            TestClientFactory::json(503, [], ['Retry-After' => '1']),
            new Response(204),
        ], $options);
        $h->client->proposals->uploadDocument(self::UUID, $upload, 'chave-upload');

        $requests = $h->apiRequests();
        self::assertCount(2, $requests);
        self::assertSame('chave-upload', $requests[1]->getHeaderLine('Idempotency-Key'));
        self::assertSame((string) $requests[0]->getBody(), (string) $requests[1]->getBody());
    }

    // ---- Redirects ---------------------------------------------------------------------------------

    public function testRedirectsAreDisabledOnApiAndTokenClients(): void
    {
        $seen = [];
        $queue = [
            TestClientFactory::tokenResponse(),
            new Response(302, ['Location' => 'https://evil.test/']),
        ];
        $handler = function ($request, array $options) use (&$seen, &$queue) {
            $seen[] = $options['allow_redirects'] ?? 'default';

            return \GuzzleHttp\Promise\Create::promiseFor(array_shift($queue));
        };
        $http = new Client(['handler' => HandlerStack::create($handler), 'http_errors' => false]);
        $client = new CredicarroClient(
            new ClientOptions(
                'id',
                self::SECRET,
                Environment::PRODUCTION,
                TestClientFactory::BASE_URL,
                TestClientFactory::fastResilience()
            ),
            $http
        );

        try {
            $client->webhooks->list();
            self::fail('Esperava CredicarroApiException.');
        } catch (CredicarroApiException $e) {
            self::assertSame(302, $e->getStatusCode());
        }

        self::assertSame([false, false], $seen);
    }

    // ---- Token -------------------------------------------------------------------------------------

    public function testTokenExpiryWithoutExpiraEmFollowsTheHalfOrMinusSixtyRule(): void
    {
        $now = 1000;

        self::assertSame($now + 3540, TokenApiClient::expiresAt(['expira_em_segundos' => 3600], $now));
        self::assertSame($now + 50, TokenApiClient::expiresAt(['expira_em_segundos' => 100], $now));
        self::assertSame($now + 30, TokenApiClient::expiresAt(['expira_em_segundos' => 60], $now));
        self::assertSame($now, TokenApiClient::expiresAt(['expira_em_segundos' => 0], $now));
        self::assertSame($now, TokenApiClient::expiresAt([], $now));
        self::assertSame(
            strtotime('2030-01-01T00:00:00+00:00'),
            TokenApiClient::expiresAt(['expira_em' => '2030-01-01T00:00:00+00:00', 'expira_em_segundos' => 10], $now)
        );
    }

    // ---- Cache PSR-16 do token ---------------------------------------------------------------------

    /**
     * @param array<string, mixed> $store
     * @param array<int, array{0: string, 1: int|null}> $sets
     * @param array<int, string> $deletes
     */
    private function memoryCache(array &$store, array &$sets, array &$deletes): CacheInterface
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(function ($key, $default = null) use (&$store) {
            return $store[$key] ?? $default;
        });
        $cache->method('set')->willReturnCallback(function ($key, $value, $ttl = null) use (&$store, &$sets) {
            $store[$key] = $value;
            $sets[] = [$key, $ttl];

            return true;
        });
        $cache->method('delete')->willReturnCallback(function ($key) use (&$store, &$deletes) {
            unset($store[$key]);
            $deletes[] = $key;

            return true;
        });

        return $cache;
    }

    /**
     * @param array<int, Response|\Throwable> $responses
     */
    private function clientWithCache(array $responses, CacheInterface $cache, string $clientId = 'id'): TestHarness
    {
        $mock = new MockHandler($responses);
        $harness = new TestHarness();
        $stack = HandlerStack::create($mock);
        $stack->push(\GuzzleHttp\Middleware::history($harness->history));
        $http = new Client(['handler' => $stack, 'http_errors' => false]);
        $harness->mock = $mock;
        $harness->client = new CredicarroClient(
            new ClientOptions(
                $clientId,
                self::SECRET,
                Environment::PRODUCTION,
                TestClientFactory::BASE_URL,
                TestClientFactory::fastResilience(),
                $cache
            ),
            $http
        );

        return $harness;
    }

    public function testTokenIsSharedThroughThePsr16CacheWithoutStoringTheSecret(): void
    {
        $store = [];
        $sets = [];
        $deletes = [];
        $cache = $this->memoryCache($store, $sets, $deletes);

        $first = $this->clientWithCache([
            TestClientFactory::tokenResponse('shared-token', gmdate('c', time() + 1800)),
            TestClientFactory::json(200, self::PAGE),
        ], $cache);
        $first->client->webhooks->list();

        // Outro "processo" (nova instância do cliente) reaproveita o token do cache: nenhuma chamada de token.
        $second = $this->clientWithCache([TestClientFactory::json(200, self::PAGE)], $cache);
        $second->client->webhooks->list();

        self::assertCount(1, $first->tokenRequests());
        self::assertCount(0, $second->tokenRequests());
        self::assertSame('Bearer shared-token', $second->lastApiRequest()->getHeaderLine('Authorization'));

        self::assertCount(1, $sets);
        self::assertMatchesRegularExpression('/^credicarro_token_[0-9a-f]{64}$/', $sets[0][0]);
        self::assertGreaterThan(1700, $sets[0][1]);
        self::assertLessThanOrEqual(1800, $sets[0][1]);
        self::assertStringNotContainsString(self::SECRET, (string) json_encode($store));
        self::assertStringNotContainsString(self::SECRET, $sets[0][0]);
    }

    public function testCacheKeyDependsOnClientIdAndEnvironment(): void
    {
        $store = [];
        $sets = [];
        $deletes = [];
        $cache = $this->memoryCache($store, $sets, $deletes);

        $a = $this->clientWithCache(
            [TestClientFactory::tokenResponse('a'), TestClientFactory::json(200, self::PAGE)],
            $cache,
            'cliente-a'
        );
        $a->client->webhooks->list();
        $b = $this->clientWithCache(
            [TestClientFactory::tokenResponse('b'), TestClientFactory::json(200, self::PAGE)],
            $cache,
            'cliente-b'
        );
        $b->client->webhooks->list();

        self::assertCount(2, $sets);
        self::assertNotSame($sets[0][0], $sets[1][0]);
        self::assertSame('Bearer b', $b->lastApiRequest()->getHeaderLine('Authorization'));
    }

    public function testCacheFailuresFallBackToTheApi(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willThrowException(new \RuntimeException('redis fora do ar'));
        $cache->method('set')->willThrowException(new \RuntimeException('redis fora do ar'));
        $cache->method('delete')->willThrowException(new \RuntimeException('redis fora do ar'));

        $h = $this->clientWithCache([
            TestClientFactory::tokenResponse('t1'),
            new Response(401),
            TestClientFactory::tokenResponse('t2'),
            TestClientFactory::json(200, self::PAGE),
        ], $cache);

        self::assertSame(0, $h->client->webhooks->list()->totalCount);
        self::assertCount(2, $h->tokenRequests());
    }

    public function testRejectedTokenIsRemovedFromTheSharedCache(): void
    {
        $store = [];
        $sets = [];
        $deletes = [];
        $cache = $this->memoryCache($store, $sets, $deletes);

        $h = $this->clientWithCache([
            TestClientFactory::tokenResponse('stale', gmdate('c', time() + 1800)),
            new Response(401),
            TestClientFactory::tokenResponse('fresh', gmdate('c', time() + 1800)),
            TestClientFactory::json(200, self::PAGE),
        ], $cache);
        $h->client->webhooks->list();

        self::assertCount(1, $deletes);
        self::assertCount(2, $sets);
        $entries = array_values($store);
        self::assertSame('fresh', $entries[0]['v']);
    }

    public function testExpiredCacheEntryIsIgnored(): void
    {
        $store = [];
        $sets = [];
        $deletes = [];
        $cache = $this->memoryCache($store, $sets, $deletes);

        $first = $this->clientWithCache([
            TestClientFactory::tokenResponse('old', gmdate('c', time() + 2)),
            TestClientFactory::json(200, self::PAGE),
        ], $cache);
        $first->client->webhooks->list();
        foreach ($store as $key => $entry) {
            $store[$key]['e'] = time() - 5;
        }

        $second = $this->clientWithCache([
            TestClientFactory::tokenResponse('new'),
            TestClientFactory::json(200, self::PAGE),
        ], $cache);
        $second->client->webhooks->list();

        self::assertCount(1, $second->tokenRequests());
    }

    // ---- E4: segredos e PII --------------------------------------------------------------------------

    public function testClientSecretNeverAppearsInStackTraces(): void
    {
        $previous = ini_set('zend.exception_ignore_args', '0');

        try {
            $h = TestClientFactory::harness([], null, false);
            $h->mock->append(TestClientFactory::json(401, ['tipo' => 'Integration.Authentication.InvalidCredentials']));

            $traces = '';
            try {
                (new CredicarroClient(
                    new ClientOptions('id', self::SECRET, Environment::PRODUCTION, TestClientFactory::BASE_URL),
                    $this->httpClientFor($h->mock)
                ))->webhooks->list();
            } catch (CredicarroAuthenticationException $e) {
                $traces .= $e->getTraceAsString();
            }

            $h2 = TestClientFactory::harness([], TestClientFactory::fastResilience(), false);
            $req = new Request('POST', 'https://x.test');
            $h2->mock->append(
                new ConnectException('boom', $req),
                new ConnectException('boom', $req),
                new ConnectException('boom', $req)
            );
            try {
                (new CredicarroClient(
                    new ClientOptions(
                        'id',
                        self::SECRET,
                        Environment::PRODUCTION,
                        TestClientFactory::BASE_URL,
                        TestClientFactory::fastResilience()
                    ),
                    $this->httpClientFor($h2->mock)
                ))->webhooks->list();
            } catch (CredicarroTimeoutException $e) {
                $traces .= $e->getTraceAsString();
                self::assertNull($e->getPrevious()->getPrevious());
            }

            self::assertNotSame('', $traces);
            self::assertStringNotContainsString(self::SECRET, $traces);
            self::assertStringNotContainsString('SEGREDO', $traces);
        } finally {
            ini_set('zend.exception_ignore_args', $previous === false ? '1' : $previous);
        }
    }

    private function httpClientFor(MockHandler $mock): Client
    {
        return new Client(['handler' => HandlerStack::create($mock), 'http_errors' => false]);
    }

    public function testClientOptionsNeverExposeTheSecretInAnyDump(): void
    {
        $options = new ClientOptions('id-publico', self::SECRET);
        $client = new CredicarroClient($options);

        ob_start();
        var_dump($options);
        var_dump($client);
        $dump = (string) ob_get_clean();

        $outputs = [
            $dump,
            print_r($options, true),
            print_r($client, true),
            (string) json_encode($options),
            (string) json_encode($client),
            var_export($options, true),
            var_export($client, true),
        ];

        foreach ($outputs as $index => $output) {
            self::assertStringNotContainsString(self::SECRET, $output, 'saída #' . $index);
        }

        self::assertSame('id-publico', $options->getClientId());
        self::assertSame(Environment::PRODUCTION, $options->getEnvironment());
    }

    public function testObjectsThatHoldSecretsRefuseSerialization(): void
    {
        $options = new ClientOptions('id-publico', self::SECRET);

        foreach ([$options, new CredicarroClient($options)] as $object) {
            $refused = false;
            try {
                serialize($object);
            } catch (\Exception $e) { // LogicException do SDK ou "Serialization of Closure" do PHP
                $refused = true;
                self::assertStringNotContainsString(self::SECRET, $e->getMessage());
            }
            self::assertTrue($refused);
        }

        $credentials = $options->credentials();
        $this->expectException(\LogicException::class);
        serialize($credentials);
    }

    public function testAccessTokenRefusesSerialization(): void
    {
        $token = new \Twila\Credicarro\Internal\Auth\AccessToken('token-secreto', time() + 60);

        $this->assertStringNotContainsString('token-secreto', print_r($token, true));
        $this->assertStringNotContainsString('token-secreto', (string) json_encode($token));
        $this->expectException(\LogicException::class);
        serialize($token);
    }

    public function testRequestsWithPersonalDataAreMaskedOnlyInDebugOutput(): void
    {
        $upsert = $this->fullUpsert();
        $account = new DepositAccount('12345678909', '001', '1234', '5', '987654', '7', DepositAccountType::CHECKING);
        $upload = $this->documentUpload();
        $webhook = new CreateWebhookRequest(
            WebhookType::PROPOSAL,
            'https://parceiro.com.br/h',
            WebhookAuthenticationType::BASIC,
            'credencial-secreta'
        );
        $result = new CreateWebhookResult(self::UUID, 'segredo-de-assinatura');

        $sensitive = [
            '12345678909', 'maria.souza@exemplo.com.br', '99876-5432', '6500', '987654', '1990-05-20',
            base64_encode('conteudo-do-documento'), 'credencial-secreta', 'segredo-de-assinatura',
        ];

        foreach ([$upsert, $account, $upload, $webhook, $result] as $object) {
            $outputs = [print_r($object, true)];
            ob_start();
            var_dump($object);
            $outputs[] = (string) ob_get_clean();

            foreach ($outputs as $output) {
                foreach ($sensitive as $value) {
                    self::assertStringNotContainsString($value, $output, get_class($object));
                }
            }
        }

        self::assertStringContainsString('***.456.789-**', print_r($upsert, true));
        self::assertStringContainsString('***.456.789-**', print_r($account, true));
        self::assertSame('12345678909', $upsert->cpf);
    }

    public function testSerializationAndJsonCarryTheRealValues(): void
    {
        $upsertJson = (string) json_encode($this->fullUpsert());
        self::assertStringContainsString('12345678909', $upsertJson);
        self::assertStringContainsString('maria.souza@exemplo.com.br', $upsertJson);
        self::assertStringContainsString('6500', $upsertJson);

        $uploadJson = (string) json_encode($this->documentUpload());
        self::assertStringContainsString(base64_encode('conteudo-do-documento'), $uploadJson);

        $serialized = serialize(new CreateWebhookResult(self::UUID, 'segredo-de-assinatura'));
        self::assertStringContainsString('segredo-de-assinatura', $serialized);
    }

    /**
     * @dataProvider roundTripProvider
     * @param object $dto
     */
    public function testSerializeRoundTripKeepsTheData($dto): void
    {
        self::assertEquals($dto, unserialize(serialize($dto)));
    }

    /**
     * @return array<string, array{0: object}>
     */
    public static function roundTripProvider(): array
    {
        $upsert = new UpsertCustomerRequest(
            '12345678909',
            'Maria',
            'Souza',
            'maria.souza@exemplo.com.br',
            '+55 (11) 99876-5432',
            '1990-05-20T00:00:00-03:00',
            new CustomerAddress('Av. Paulista', 'Bela Vista', 'São Paulo', 'SP', '01311000'),
            new ProfessionalData(OccupationType::EMPLOYEE, 6500.0)
        );

        return [
            'upsert' => [$upsert],
            'create customer' => [
                new CreateCustomerRequest('12345678909', 'M', 'S', 'm@e.com', '+55 (11) 99876-5432', 'x'),
            ],
            'proposal request' => [new CreateProposalRequest('12345678909', self::UUID)],
            'deposit account' => [new DepositAccount('12345678909', '001', '1234', '5', '987654', '7', 'CORRENTE')],
            'document file' => [DocumentFile::fromBytes('conteudo-do-documento', 'cnh.pdf')],
            'document upload' => [new ProposalDocumentUpload(
                ProposalParticipant::BUYER,
                DocumentType::CNH,
                DocumentFile::fromBytes('conteudo-do-documento', 'cnh.pdf')
            )],
            'webhook request' => [new CreateWebhookRequest(
                WebhookType::PROPOSAL,
                'https://parceiro.com.br/h',
                WebhookAuthenticationType::BASIC,
                'credencial-secreta'
            )],
            'webhook result' => [new CreateWebhookResult(self::UUID, 'segredo-de-assinatura')],
        ];
    }

    public function testResponseModelsSurviveSerializationWithRealValues(): void
    {
        $event = \Twila\Credicarro\Webhooks\CredicarroWebhookEvent::parseUnverified(
            '{"id_proposta":"' . self::UUID . '","enum_status":2,"status":"APROVADA","motivo_evento":"STATUS_ALTERADO",'
            . '"comprador":{"cpf":"12345678909","enum_biometria":3,"biometria":"APROVADA","documentos":[]},'
            . '"loja":null,"vendedor":null}'
        );

        $copy = unserialize(serialize($event));

        self::assertEquals($event, $copy);
        self::assertNotNull($copy->buyer);
        self::assertSame('12345678909', $copy->buyer->document);
        self::assertStringNotContainsString('12345678909', print_r($copy, true));
    }

    private function fullUpsert(): UpsertCustomerRequest
    {
        return new UpsertCustomerRequest(
            '12345678909',
            'Maria',
            'Souza',
            'maria.souza@exemplo.com.br',
            '+55 (11) 99876-5432',
            '1990-05-20T00:00:00-03:00',
            new CustomerAddress('Av. Paulista', 'Bela Vista', 'São Paulo', 'SP', '01311000'),
            new ProfessionalData(OccupationType::EMPLOYEE, 6500.0)
        );
    }

    private function documentUpload(): ProposalDocumentUpload
    {
        return new ProposalDocumentUpload(
            ProposalParticipant::BUYER,
            DocumentType::CNH,
            DocumentFile::fromBytes('conteudo-do-documento', 'cnh.pdf')
        );
    }

    public function testCpfMasks(): void
    {
        self::assertSame('***.456.789-**', Mask::cpf('12345678909'));
        self::assertSame('***.456.789-**', Mask::cpf('123.456.789-09'));
        self::assertSame('***', Mask::cpf('123'));
        self::assertSame('***', Mask::cpf(null));
        self::assertSame('***', Mask::full('x'));
        self::assertNull(Mask::full(null));
    }

    public function testResponseModelsMaskCpfInDebugOutput(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, [
            'itens' => [[
                'id_proposta' => self::UUID,
                'numero' => 1,
                'id_simulacao' => self::UUID,
                'tipo' => 'Financing',
                'status' => 'Approved',
                'comprador' => ['cpf' => '123.456.789-01'],
                'data_criacao' => '2025-10-17T19:05:08.984Z',
            ]],
            'pagina' => self::PAGE['pagina'],
        ])]);

        $summary = $h->client->proposals->list()->items[0];

        self::assertStringNotContainsString('789-01', print_r($summary, true));
        self::assertStringContainsString('***.456.789-**', print_r($summary, true));
        self::assertStringContainsString('789-01', (string) json_encode($summary));
        self::assertSame('123.456.789-01', $summary->buyerCpf);
    }

    // ---- Idempotency-Key -----------------------------------------------------------------------------

    /**
     * @dataProvider invalidKeysProvider
     */
    public function testInvalidExplicitIdempotencyKeysFailBeforeAnyRequest(string $key): void
    {
        $h = TestClientFactory::harness([]);

        try {
            $h->client->customers->upsert($this->upsertRequest(), $key);
            self::fail('Esperava CredicarroValidationException.');
        } catch (CredicarroValidationException $e) {
            self::assertSame('Validation.IdempotencyKey.Invalid', $e->getErrorCode());
        }

        self::assertCount(0, $h->history);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidKeysProvider(): array
    {
        return [
            'empty' => [''],
            'only spaces' => ['   '],
            'newline' => ["abc\ndef"],
            'tab' => ["abc\tdef"],
            'del' => ["abc\x7Fdef"],
            'non ascii' => ['chave-ção'],
            'too long' => [str_repeat('a', 201)],
        ];
    }

    public function testBoundaryIdempotencyKeysAreAccepted(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(200, ['id_cliente' => self::UUID]),
            TestClientFactory::json(200, ['id_cliente' => self::UUID]),
        ]);

        $h->client->customers->upsert($this->upsertRequest(), str_repeat('a', 200));
        $h->client->customers->upsert($this->upsertRequest(), 'com espaco ~!');

        self::assertCount(2, $h->apiRequests());
    }

    // ---- Webhook -------------------------------------------------------------------------------------

    public function testVerifyAuthorizationHandlesDifferentLengths(): void
    {
        self::assertFalse(CredicarroWebhookEvent::verifyAuthorization('curto', 'Bearer muito-mais-longo-que-o-curto'));
        self::assertFalse(CredicarroWebhookEvent::verifyAuthorization('Bearer muito-mais-longo-que-o-esperado', 'x'));
        self::assertTrue(CredicarroWebhookEvent::verifyAuthorization('Bearer abc', 'Bearer abc'));
        self::assertFalse(CredicarroWebhookEvent::verifyAuthorization('Bearer abc', ''));
    }

    public function testNegativeToleranceIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CredicarroWebhookEvent::verifySignature('{}', 't=1,v1=ab', 'segredo', -1);
    }

    /**
     * @dataProvider invalidProposalIdsProvider
     */
    public function testWebhookBodyWithoutAValidProposalUuidIsRejected(string $body): void
    {
        $this->expectException(\Twila\Credicarro\Errors\CredicarroWebhookSignatureException::class);
        CredicarroWebhookEvent::parseUnverified($body);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidProposalIdsProvider(): array
    {
        return [
            'not uuid' => ['{"id_proposta":"x","enum_status":1}'],
            'number' => ['{"id_proposta":123,"enum_status":1}'],
            'missing' => ['{"enum_status":1}'],
            'path' => ['{"id_proposta":"../../etc","enum_status":1}'],
        ];
    }

    // ---- DocumentFile --------------------------------------------------------------------------------

    public function testDocumentFileConstructionIsAlwaysValidated(): void
    {
        $this->expectException(CredicarroValidationException::class);
        new DocumentFile('conteudo', 'arquivo.exe');
    }

    public function testFromStreamReadsInChunksUntilTheLimit(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        fwrite($resource, str_repeat('b', DocumentFile::MAX_BYTES + 5));
        rewind($resource);

        try {
            DocumentFile::fromStream($resource, 'grande.pdf');
            self::fail('Esperava CredicarroValidationException.');
        } catch (CredicarroValidationException $e) {
            self::assertSame('Validation.File.TooLarge', $e->getErrorCode());
        } finally {
            fclose($resource);
        }

        $small = fopen('php://temp', 'r+');
        self::assertIsResource($small);
        fwrite($small, str_repeat('c', 200000));
        rewind($small);
        self::assertSame(200000, DocumentFile::fromStream($small, 'ok.pdf')->sizeBytes);
        fclose($small);
    }

    public function testFromFileDoesNotTrustFilesizeAndRejectsOversizedFiles(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'cred');
        file_put_contents($path, str_repeat('d', DocumentFile::MAX_BYTES + 1));

        try {
            DocumentFile::fromFile($path, 'grande.pdf');
            self::fail('Esperava CredicarroValidationException.');
        } catch (CredicarroValidationException $e) {
            self::assertSame('Validation.File.TooLarge', $e->getErrorCode());
        } finally {
            unlink($path);
        }
    }

    // ---- Item 16: erros do endpoint de token ---------------------------------------------------------

    /**
     * @dataProvider tokenAuthFailuresProvider
     */
    public function testTokenEndpoint400And401AreAuthenticationFailures(int $status): void
    {
        $h = TestClientFactory::harness([], null, false);
        $h->mock->append(TestClientFactory::json($status, ['tipo' => 'Integration.Authentication.InvalidCredentials']));

        $this->expectException(CredicarroAuthenticationException::class);
        $h->client->webhooks->list();
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function tokenAuthFailuresProvider(): array
    {
        return ['400' => [400], '401' => [401]];
    }

    /**
     * @dataProvider tokenApiFailuresProvider
     */
    public function testTokenEndpointOtherStatusesAreApiExceptionsWithStatusAndType(int $status, string $type): void
    {
        $h = TestClientFactory::harness([], new ResilienceOptions(5000, 1000, 60000, 1, 1), false);
        $h->mock->append(TestClientFactory::json($status, ['tipo' => $type, 'titulo' => 'x']));

        try {
            $h->client->webhooks->list();
            self::fail('Esperava CredicarroApiException.');
        } catch (CredicarroAuthenticationException $e) {
            self::fail('Não deveria ser falha de autenticação: ' . $status);
        } catch (CredicarroApiException $e) {
            self::assertSame($status, $e->getStatusCode());
            self::assertSame($type, $e->getErrorCode());
        }

        self::assertCount(0, $h->apiRequests());
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function tokenApiFailuresProvider(): array
    {
        return [
            '302' => [302, 'Redirect'],
            '403' => [403, 'Integration.Access.Inactive'],
            '404' => [404, 'Nao.Encontrado'],
            '500' => [500, 'Erro.Interno'],
            '503' => [503, 'Indisponivel'],
        ];
    }

    // ---- Dependências -------------------------------------------------------------------------------

    public function testInstalledGuzzleIsNotAVulnerableVersion(): void
    {
        $version = \Composer\InstalledVersions::getVersion('guzzlehttp/guzzle');

        self::assertNotNull($version);
        self::assertTrue(version_compare((string) $version, '7.4.5', '>='), 'Guzzle ' . $version);
    }

    private function upsertRequest(): UpsertCustomerRequest
    {
        return new UpsertCustomerRequest(
            '12345678909',
            'Maria',
            'Souza',
            'maria.souza@exemplo.com.br',
            '+55 (11) 99876-5432',
            '1990-05-20T00:00:00-03:00',
            new CustomerAddress('Av. Paulista', 'Bela Vista', 'São Paulo', 'SP', '01311000'),
            new ProfessionalData(OccupationType::EMPLOYEE, 6500.0)
        );
    }
}
