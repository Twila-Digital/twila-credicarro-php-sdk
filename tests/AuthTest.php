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
use Twila\Credicarro\Errors\CredicarroAuthenticationException;
use Twila\Credicarro\Errors\CredicarroTimeoutException;
use Twila\Credicarro\Internal\Auth\AccessTokenProvider;
use Twila\Credicarro\Internal\Auth\Credentials;
use Twila\Credicarro\Internal\Auth\TokenApiClient;
use Twila\Credicarro\Internal\Resilience\ResiliencePipeline;

final class AuthTest extends TestCase
{
    private const WEBHOOKS_PAGE = [
        'itens' => [],
        'pagina' => ['tem_proximo' => false, 'tem_anterior' => false, 'numero' => 1, 'tamanho' => 10, 'total' => 0],
    ];

    public function testTokenRequestSendsCredentialsAndHasNoAuthorizationHeader(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, self::WEBHOOKS_PAGE)]);

        $h->client->webhooks->list();

        $tokenRequest = $h->tokenRequests()[0];
        self::assertSame('POST', $tokenRequest->getMethod());
        self::assertSame('/integration/v1/authentication/accesstoken', $tokenRequest->getUri()->getPath());
        self::assertSame('', $tokenRequest->getHeaderLine('Authorization'));
        self::assertSame(
            ['clientId' => 'test-client-id', 'clientSecret' => 'test-client-secret'],
            TestClientFactory::bodyOf($tokenRequest)
        );
        self::assertSame('Bearer test-token', $h->lastApiRequest()->getHeaderLine('Authorization'));
    }

    public function testTokenIsCachedBetweenCalls(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(200, self::WEBHOOKS_PAGE),
            TestClientFactory::json(200, self::WEBHOOKS_PAGE),
        ]);

        $h->client->webhooks->list();
        $h->client->webhooks->list();

        self::assertCount(1, $h->tokenRequests());
        self::assertCount(2, $h->apiRequests());
    }

    public function testExpiredTokenIsRenewedBeforeTheNextCall(): void
    {
        $h = TestClientFactory::harness([], null, false);
        $h->mock->append(
            TestClientFactory::tokenResponse('first-token', '2000-01-01T00:00:00+00:00'),
            TestClientFactory::json(200, self::WEBHOOKS_PAGE),
            TestClientFactory::tokenResponse('second-token'),
            TestClientFactory::json(200, self::WEBHOOKS_PAGE)
        );

        $h->client->webhooks->list();
        $h->client->webhooks->list();

        self::assertCount(2, $h->tokenRequests());
        $api = $h->apiRequests();
        self::assertSame('Bearer first-token', $api[0]->getHeaderLine('Authorization'));
        self::assertSame('Bearer second-token', $api[1]->getHeaderLine('Authorization'));
    }

    public function testExpiresAtIsUsedWhenPresentAndKeepsTokenCached(): void
    {
        $future = gmdate('c', time() + 1800);
        $h = TestClientFactory::harness([], null, false);
        $h->mock->append(
            TestClientFactory::tokenResponse('first-token', $future),
            TestClientFactory::json(200, self::WEBHOOKS_PAGE),
            TestClientFactory::json(200, self::WEBHOOKS_PAGE)
        );

        $h->client->webhooks->list();
        $h->client->webhooks->list();

        self::assertCount(1, $h->tokenRequests());
    }

    public function testExpirySecondsFallbackLeavesA60SecondMargin(): void
    {
        $short = TestClientFactory::json(200, ['token_de_acesso' => 'short', 'expira_em_segundos' => 0]);
        $h = TestClientFactory::harness([], null, false);
        $h->mock->append(
            $short,
            TestClientFactory::json(200, self::WEBHOOKS_PAGE),
            TestClientFactory::tokenResponse('long'),
            TestClientFactory::json(200, self::WEBHOOKS_PAGE)
        );

        $h->client->webhooks->list();
        $h->client->webhooks->list();

        self::assertCount(2, $h->tokenRequests());
    }

    public function test401RefreshesTokenAndRetriesOnce(): void
    {
        $h = TestClientFactory::harness([], null, false);
        $h->mock->append(
            TestClientFactory::tokenResponse('stale-token'),
            new Response(401),
            TestClientFactory::tokenResponse('fresh-token'),
            TestClientFactory::json(200, self::WEBHOOKS_PAGE)
        );

        $page = $h->client->webhooks->list();

        self::assertSame(0, $page->totalCount);
        self::assertCount(2, $h->tokenRequests());
        $api = $h->apiRequests();
        self::assertSame('Bearer stale-token', $api[0]->getHeaderLine('Authorization'));
        self::assertSame('Bearer fresh-token', $api[1]->getHeaderLine('Authorization'));
    }

    public function testSecond401RaisesAuthenticationExceptionWithoutLoopingForever(): void
    {
        $h = TestClientFactory::harness([], null, false);
        $h->mock->append(
            TestClientFactory::tokenResponse('stale-token'),
            new Response(401),
            TestClientFactory::tokenResponse('fresh-token'),
            new Response(401)
        );

        try {
            $h->client->webhooks->list();
            self::fail('Esperava CredicarroAuthenticationException.');
        } catch (CredicarroAuthenticationException $e) {
            self::assertSame(401, $e->getStatusCode());
            self::assertStringNotContainsString('fresh-token', $e->getMessage());
            self::assertStringNotContainsString('test-client-secret', $e->getMessage());
        }

        self::assertCount(2, $h->apiRequests());
    }

    public function testInvalidCredentialsRaiseAuthenticationException(): void
    {
        $h = TestClientFactory::harness([], null, false);
        $h->mock->append(TestClientFactory::json(401, [
            'tipo' => 'Integration.Authentication.InvalidCredentials',
            'titulo' => 'Credenciais inválidas',
            'status' => 401,
            'detalhe' => 'ClientId ou ClientSecret inválidos.',
        ]));

        try {
            $h->client->webhooks->list();
            self::fail('Esperava CredicarroAuthenticationException.');
        } catch (CredicarroAuthenticationException $e) {
            self::assertSame('Integration.Authentication.InvalidCredentials', $e->getErrorCode());
            self::assertStringContainsString('Integration.Authentication.InvalidCredentials', $e->getMessage());
            self::assertStringNotContainsString('test-client-secret', $e->getMessage());
        }

        self::assertCount(0, $h->apiRequests());
    }

    public function test400OnTokenEndpointIsAnAuthenticationFailure(): void
    {
        $h = TestClientFactory::harness([], null, false);
        $h->mock->append(TestClientFactory::json(400, [
            'tipo' => 'Validation.Field.Required',
            'titulo' => 'Requisição inválida',
            'status' => 400,
            'campo' => 'clientSecret',
        ]));

        $this->expectException(CredicarroAuthenticationException::class);
        $h->client->webhooks->list();
    }

    public function testTokenEndpointIsRetriedOnTransientFailure(): void
    {
        $h = TestClientFactory::harness([], null, false);
        $h->mock->append(
            new Response(503),
            TestClientFactory::tokenResponse(),
            TestClientFactory::json(200, self::WEBHOOKS_PAGE)
        );

        $h->client->webhooks->list();

        self::assertCount(2, $h->tokenRequests());
    }

    public function testEmptyTokenResponseRaisesAuthenticationException(): void
    {
        $h = TestClientFactory::harness([], null, false);
        $h->mock->append(TestClientFactory::json(200, ['tipo_de_token' => 'Bearer']));

        $this->expectException(CredicarroAuthenticationException::class);
        $h->client->webhooks->list();
    }

    public function testNetworkFailureOnTokenRaisesTimeoutException(): void
    {
        $h = TestClientFactory::harness([], TestClientFactory::fastResilience(), false);
        $request = new Request('POST', 'https://sdk-test.local');
        $h->mock->append(
            new ConnectException('boom', $request),
            new ConnectException('boom', $request),
            new ConnectException('boom', $request)
        );

        $this->expectException(CredicarroTimeoutException::class);
        $h->client->webhooks->list();
    }

    public function testInvalidateOnlyDropsTheCacheWhenTheRejectedTokenIsStillCurrent(): void
    {
        $mock = new MockHandler([
            TestClientFactory::tokenResponse('token-1'),
            TestClientFactory::tokenResponse('token-2'),
        ]);
        $http = new Client(['handler' => HandlerStack::create($mock), 'http_errors' => false]);
        $api = new TokenApiClient(
            TestClientFactory::BASE_URL,
            1000,
            new ResiliencePipeline(TestClientFactory::fastResilience()),
            $http
        );
        $provider = new AccessTokenProvider($api, new Credentials('id', 'secret'));

        self::assertSame('token-1', $provider->getToken());

        $provider->invalidate('token-velho');
        self::assertSame('token-1', $provider->getToken());
        self::assertCount(1, $mock);

        $provider->invalidate('token-1');
        self::assertSame('token-2', $provider->getToken());
        self::assertCount(0, $mock);
    }
}
