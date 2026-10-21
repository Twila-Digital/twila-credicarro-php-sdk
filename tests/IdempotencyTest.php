<?php

declare(strict_types=1);

namespace Twila\Credicarro\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Twila\Credicarro\Config\ResilienceOptions;
use Twila\Credicarro\Customers\CreateCustomerRequest;
use Twila\Credicarro\Customers\CustomerAddress;
use Twila\Credicarro\Customers\OccupationType;
use Twila\Credicarro\Customers\ProfessionalData;
use Twila\Credicarro\Customers\UpsertCustomerRequest;
use Twila\Credicarro\Errors\CredicarroApiException;
use Twila\Credicarro\Errors\CredicarroTimeoutException;
use Twila\Credicarro\Internal\Idempotency\IdempotencyClassifier;
use Twila\Credicarro\Proposals\CreateProposalRequest;
use Twila\Credicarro\Proposals\DepositAccount;
use Twila\Credicarro\Proposals\DepositAccountType;
use Twila\Credicarro\Proposals\DocumentFile;
use Twila\Credicarro\Proposals\DocumentType;
use Twila\Credicarro\Proposals\ProposalDocumentUpload;
use Twila\Credicarro\Proposals\ProposalParticipant;
use Twila\Credicarro\Proposals\VehicleDetails;
use Twila\Credicarro\Simulations\CreateFinancingSimulationRequest;
use Twila\Credicarro\Webhooks\CreateWebhookRequest;
use Twila\Credicarro\Webhooks\UpdateWebhookRequest;
use Twila\Credicarro\Webhooks\WebhookAuthenticationType;
use Twila\Credicarro\Webhooks\WebhookType;

final class IdempotencyTest extends TestCase
{
    private const UUID = TestClientFactory::UUID;

    /**
     * @dataProvider requiresIdempotencyKeyProvider
     */
    public function testRequiresIdempotencyKey(string $method, string $path, bool $expected): void
    {
        self::assertSame($expected, IdempotencyClassifier::requiresIdempotencyKey($method, $path));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function requiresIdempotencyKeyProvider(): array
    {
        return [
            'create customer' => ['POST', 'v1/customer', true],
            'customer address' => ['POST', 'v1/customer/address', true],
            'customer professional' => ['POST', 'v1/customer/professional', true],
            'unified is NEVER automatic' => ['POST', 'v1/customer/unified', false],
            'v1 simulation financing' => ['POST', 'v1/proposal/simulation/financing', true],
            'v1 simulation refinancing' => ['POST', 'v1/proposal/simulation/refinancing', true],
            'v2 simulation financing' => ['POST', 'v2/proposal/simulation/financing', true],
            'v2 simulation refinancing' => ['POST', 'v2/proposal/simulation/refinancing', true],
            'v1 proposal' => ['POST', 'v1/proposal', true],
            'v2 proposal' => ['POST', 'v2/proposal', true],
            'document upload' => ['POST', 'v2/proposal/' . self::UUID . '/document', true],
            'vehicle detail' => ['POST', 'v2/proposal/' . self::UUID . '/vehicle-detail', false],
            'deposit account' => ['POST', 'v2/proposal/' . self::UUID . '/deposit-account', false],
            'create webhook' => ['POST', 'v1/webhook', false],
            'token' => ['POST', 'v1/authentication/accesstoken', false],
            'get proposal' => ['GET', 'v2/proposal/' . self::UUID, false],
        ];
    }

    /**
     * @dataProvider retrySafeProvider
     */
    public function testIsRetrySafe(string $method, string $path, bool $hasKey, bool $expected): void
    {
        self::assertSame($expected, IdempotencyClassifier::isRetrySafe($method, $path, $hasKey));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool, 3: bool}>
     */
    public static function retrySafeProvider(): array
    {
        return [
            'get' => ['GET', 'v2/proposal/' . self::UUID, false, true],
            'head' => ['HEAD', 'v1/proposal', false, true],
            'token post' => ['POST', 'v1/authentication/accesstoken', false, true],
            'post with key' => ['POST', 'v2/proposal', true, true],
            'post without key' => ['POST', 'v2/proposal', false, false],
            'unified without key' => ['POST', 'v1/customer/unified', false, false],
            'unified with explicit key' => ['POST', 'v1/customer/unified', true, true],
            'vehicle detail replaces' => ['POST', 'v2/proposal/' . self::UUID . '/vehicle-detail', false, true],
            'deposit account replaces' => ['POST', 'v2/proposal/' . self::UUID . '/deposit-account', false, true],
            'create webhook not safe' => ['POST', 'v1/webhook', false, false],
            'put webhook' => ['PUT', 'v1/webhook/' . self::UUID, false, true],
            'delete webhook' => ['DELETE', 'v1/webhook/' . self::UUID, false, true],
            'put elsewhere' => ['PUT', 'v2/proposal/' . self::UUID, false, false],
        ];
    }

    public function testUnifiedWithoutKeyHasNoIdempotencyHeaderAndIsNotRetried(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(503, ['tipo' => 'Servico.Indisponivel'])]);

        try {
            $h->client->customers->upsert($this->upsertRequest());
            self::fail('Esperava CredicarroApiException.');
        } catch (CredicarroApiException $e) {
            self::assertSame(503, $e->getStatusCode());
        }

        $requests = $h->apiRequests();
        self::assertCount(1, $requests);
        self::assertFalse($requests[0]->hasHeader('Idempotency-Key'));
    }

    public function testUnifiedWithoutKeyDoesNotRetryNetworkFailuresEither(): void
    {
        $h = TestClientFactory::harness([
            new \GuzzleHttp\Exception\ConnectException('boom', new \GuzzleHttp\Psr7\Request('POST', 'https://x.test')),
            TestClientFactory::json(200, ['id_cliente' => self::UUID]),
        ]);

        try {
            $h->client->customers->upsert($this->upsertRequest());
            self::fail('Esperava CredicarroTimeoutException.');
        } catch (CredicarroTimeoutException $e) {
            self::assertCount(1, $h->mock);
        }
    }

    public function testUnifiedWithoutExplicitKeyNeverGetsAnAutomaticKey(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, ['id_cliente' => self::UUID])]);

        $h->client->customers->upsert($this->upsertRequest());

        self::assertFalse($h->lastApiRequest()->hasHeader('Idempotency-Key'));
    }

    public function testUnifiedWithExplicitKeySendsItAndRetriesWithTheSameKey(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(503, []),
            TestClientFactory::json(200, ['id_cliente' => self::UUID]),
        ]);

        $id = $h->client->customers->upsert($this->upsertRequest(), 'minha-chave-1');

        self::assertSame(self::UUID, $id);
        $requests = $h->apiRequests();
        self::assertCount(2, $requests);
        self::assertSame('minha-chave-1', $requests[0]->getHeaderLine('Idempotency-Key'));
        self::assertSame('minha-chave-1', $requests[1]->getHeaderLine('Idempotency-Key'));
    }

    public function testAutomaticKeyIsGeneratedOncePerLogicalCallAndReusedInEveryAttempt(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(502, []),
            TestClientFactory::json(500, []),
            TestClientFactory::json(201, ['id_proposta' => self::UUID]),
        ]);

        $id = $h->client->proposals->create(new CreateProposalRequest('12345678909', self::UUID));

        self::assertSame(self::UUID, $id);
        $requests = $h->apiRequests();
        self::assertCount(3, $requests);
        $key = $requests[0]->getHeaderLine('Idempotency-Key');
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $key
        );
        self::assertSame($key, $requests[1]->getHeaderLine('Idempotency-Key'));
        self::assertSame($key, $requests[2]->getHeaderLine('Idempotency-Key'));
    }

    public function testDifferentLogicalCallsGetDifferentAutomaticKeys(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(201, ['id_proposta' => self::UUID]),
            TestClientFactory::json(201, ['id_proposta' => self::UUID]),
        ]);

        $h->client->proposals->create(new CreateProposalRequest('12345678909', self::UUID));
        $h->client->proposals->create(new CreateProposalRequest('12345678909', self::UUID));

        $requests = $h->apiRequests();
        self::assertNotSame(
            $requests[0]->getHeaderLine('Idempotency-Key'),
            $requests[1]->getHeaderLine('Idempotency-Key')
        );
    }

    public function testDisableAutomaticIdempotencyKeyRemovesTheHeaderAndTheRetry(): void
    {
        $resilience = new ResilienceOptions(5000, 1000, 60000, 3, 1, 0.5, 30000, 10, 15000, true, true);
        $h = TestClientFactory::harness([TestClientFactory::json(503, [])], $resilience);

        try {
            $h->client->proposals->create(new CreateProposalRequest('12345678909', self::UUID));
            self::fail('Esperava CredicarroApiException.');
        } catch (CredicarroApiException $e) {
            self::assertSame(503, $e->getStatusCode());
        }

        $requests = $h->apiRequests();
        self::assertCount(1, $requests);
        self::assertFalse($requests[0]->hasHeader('Idempotency-Key'));
    }

    /**
     * @dataProvider automaticKeyOperationsProvider
     * @param callable(\Twila\Credicarro\CredicarroClient): mixed $operation
     */
    public function testAutomaticKeyIsSentOnTheDocumentedRoutes(callable $operation, string $path): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, [
            'id_cliente' => self::UUID,
            'id_proposta' => self::UUID,
            'simulacoes' => [],
        ])]);

        $operation($h->client);

        $request = $h->lastApiRequest();
        self::assertSame('/integration/' . $path, $request->getUri()->getPath());
        self::assertNotSame('', $request->getHeaderLine('Idempotency-Key'));
    }

    /**
     * @return array<string, array{0: callable, 1: string}>
     */
    public static function automaticKeyOperationsProvider(): array
    {
        $address = new CustomerAddress('Av. Paulista', 'Bela Vista', 'São Paulo', 'SP', '01311000');

        return [
            'customer create' => [
                static function ($c) {
                    return $c->customers->create(
                        new CreateCustomerRequest('1', 'M', 'S', 'm@e.com', '+55 (11) 99876-5432', 'x')
                    );
                },
                'v1/customer',
            ],
            'customer address' => [
                static function ($c) use ($address) {
                    $c->customers->upsertAddress('12345678909', $address);
                },
                'v1/customer/address',
            ],
            'customer professional' => [
                static function ($c) {
                    $c->customers->upsertProfessionalData(
                        '12345678909',
                        new ProfessionalData(OccupationType::EMPLOYEE, 5000.0)
                    );
                },
                'v1/customer/professional',
            ],
            'v2 financing' => [
                static function ($c) {
                    $c->simulations->createFinancing(
                        new CreateFinancingSimulationRequest(
                            '1',
                            'ABC1D23',
                            1000.0,
                            0.0,
                            'SP',
                            false,
                            '11222333000181'
                        )
                    );
                },
                'v2/proposal/simulation/financing',
            ],
            'v1 financing' => [
                static function ($c) {
                    $c->v1->simulations->createFinancing(
                        new CreateFinancingSimulationRequest('1', 'ABC1D23', 1000.0, 0.0, 'SP', false)
                    );
                },
                'v1/proposal/simulation/financing',
            ],
            'v1 proposal' => [
                static function ($c) {
                    $c->v1->proposals->create(new CreateProposalRequest('1', self::UUID));
                },
                'v1/proposal',
            ],
        ];
    }

    public function testDocumentUploadReusesTheSameKeyAcrossAttempts(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(502, ['tipo' => 'Document.UploadFailed']),
            new Response(204),
        ]);

        $h->client->proposals->uploadDocument(self::UUID, $this->documentUpload());

        $requests = $h->apiRequests();
        self::assertCount(2, $requests);
        $key = $requests[0]->getHeaderLine('Idempotency-Key');
        self::assertNotSame('', $key);
        self::assertSame($key, $requests[1]->getHeaderLine('Idempotency-Key'));
    }

    public function testDocumentUploadUsesTheExplicitKeyEvenWhenAutomaticKeysAreDisabled(): void
    {
        $resilience = new ResilienceOptions(5000, 1000, 60000, 3, 1, 0.5, 30000, 10, 15000, true, true);
        $h = TestClientFactory::harness([new Response(204)], $resilience);

        $h->client->proposals->uploadDocument(self::UUID, $this->documentUpload(), 'doc-123');

        self::assertSame('doc-123', $h->lastApiRequest()->getHeaderLine('Idempotency-Key'));
    }

    public function testDocumentUploadAlwaysSendsAKeyBecauseTheApiRequiresIt(): void
    {
        $resilience = new ResilienceOptions(5000, 1000, 60000, 3, 1, 0.5, 30000, 10, 15000, true, true);
        $h = TestClientFactory::harness([new Response(204)], $resilience);

        $h->client->proposals->uploadDocument(self::UUID, $this->documentUpload());

        self::assertNotSame('', $h->lastApiRequest()->getHeaderLine('Idempotency-Key'));
    }

    public function testVehicleDetailAndDepositAccountAreRetriedWithoutKey(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(503, []),
            new Response(204),
            TestClientFactory::json(503, []),
            new Response(204),
        ]);

        $h->client->proposals->sendVehicleDetails(
            self::UUID,
            new VehicleDetails('12345678900', '9BWZZZ377VT004251', 'ABC1D23', 'PRATA')
        );
        $h->client->proposals->sendDepositAccount(
            self::UUID,
            new DepositAccount('12345678909', '001', '1234', '5', '123456', '7', DepositAccountType::CHECKING)
        );

        $requests = $h->apiRequests();
        self::assertCount(4, $requests);
        foreach ($requests as $request) {
            self::assertFalse($request->hasHeader('Idempotency-Key'));
        }
    }

    public function testCreateWebhookIsNotRetriedAndHasNoKey(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(503, ['tipo' => 'Organization.Webhook.CredentialProtectionUnavailable']),
        ]);

        try {
            $h->client->webhooks->create(
                new CreateWebhookRequest(
                    WebhookType::PROPOSAL,
                    'https://parceiro.com.br/hook',
                    WebhookAuthenticationType::NONE
                )
            );
            self::fail('Esperava CredicarroApiException.');
        } catch (CredicarroApiException $e) {
            self::assertSame(503, $e->getStatusCode());
        }

        $requests = $h->apiRequests();
        self::assertCount(1, $requests);
        self::assertFalse($requests[0]->hasHeader('Idempotency-Key'));
    }

    public function testUpdateAndDeleteWebhookAreRetried(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(503, []),
            new Response(204),
            TestClientFactory::json(503, []),
            new Response(204),
        ]);

        $h->client->webhooks->update(
            self::UUID,
            new UpdateWebhookRequest(WebhookType::PROPOSAL, 'https://parceiro.com.br/hook')
        );
        $h->client->webhooks->delete(self::UUID);

        self::assertCount(4, $h->apiRequests());
    }

    public function testExplicitKeyIsValidatedLocally(): void
    {
        $h = TestClientFactory::harness([]);

        $this->expectException(\Twila\Credicarro\Errors\CredicarroValidationException::class);
        $h->client->customers->upsert($this->upsertRequest(), str_repeat('x', 201));
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
            new CustomerAddress('Av. Paulista', 'Bela Vista', 'São Paulo', 'SP', '01311000', '1578'),
            new ProfessionalData(OccupationType::EMPLOYEE, 6500.0)
        );
    }

    private function documentUpload(): ProposalDocumentUpload
    {
        return new ProposalDocumentUpload(
            ProposalParticipant::BUYER,
            DocumentType::CNH,
            DocumentFile::fromBytes('%PDF-1.7 conteudo', 'cnh-comprador.pdf')
        );
    }
}
