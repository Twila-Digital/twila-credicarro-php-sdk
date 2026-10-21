<?php

declare(strict_types=1);

namespace Twila\Credicarro\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Twila\Credicarro\Config\ResilienceOptions;
use Twila\Credicarro\Errors\CredicarroApiException;
use Twila\Credicarro\Errors\CredicarroRateLimitException;
use Twila\Credicarro\Errors\CredicarroTimeoutException;
use Twila\Credicarro\Internal\Http\ApiResponse;
use Twila\Credicarro\Internal\Resilience\ResiliencePipeline;

final class ResilienceTest extends TestCase
{
    private function fastOptions(): ResilienceOptions
    {
        return new ResilienceOptions(5000, 1000, 60000, 3, 1);
    }

    private function response(int $statusCode): ApiResponse
    {
        return new ApiResponse($statusCode, [], []);
    }

    public function testSuccessfulResponseDoesNotRetry(): void
    {
        $pipeline = new ResiliencePipeline($this->fastOptions());
        $calls = 0;

        $result = $pipeline->execute(true, function () use (&$calls) {
            $calls++;
            return $this->response(200);
        });

        self::assertSame(200, $result->statusCode);
        self::assertSame(1, $calls);
    }

    /**
     * @dataProvider transientStatusProvider
     */
    public function testTransientResponseRetriesWhenRetrySafe(int $status): void
    {
        $pipeline = new ResiliencePipeline($this->fastOptions());
        $calls = 0;

        $result = $pipeline->execute(true, function () use (&$calls, $status) {
            $calls++;
            return $this->response($calls < 3 ? $status : 200);
        });

        self::assertSame(200, $result->statusCode);
        self::assertSame(3, $calls);
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function transientStatusProvider(): array
    {
        return [
            '408' => [408],
            '429' => [429],
            '500 (RetryOn500 padrão)' => [500],
            '502' => [502],
            '503' => [503],
            '504' => [504],
        ];
    }

    /**
     * @dataProvider nonTransientStatusProvider
     */
    public function testNonTransientStatusIsNeverRetried(int $status): void
    {
        $pipeline = new ResiliencePipeline($this->fastOptions());
        $calls = 0;

        $result = $pipeline->execute(true, function () use (&$calls, $status) {
            $calls++;
            return $this->response($status);
        });

        self::assertSame($status, $result->statusCode);
        self::assertSame(1, $calls);
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function nonTransientStatusProvider(): array
    {
        return [
            '400' => [400],
            '401' => [401],
            '403' => [403],
            '404' => [404],
            '409' => [409],
            '422' => [422],
        ];
    }

    public function testRetryOn500CanBeDisabled(): void
    {
        $options = new ResilienceOptions(5000, 1000, 60000, 3, 1, 0.5, 30000, 10, 15000, false);
        $pipeline = new ResiliencePipeline($options);
        $calls = 0;

        $result = $pipeline->execute(true, function () use (&$calls) {
            $calls++;
            return $this->response(500);
        });

        self::assertSame(500, $result->statusCode);
        self::assertSame(1, $calls);
    }

    public function testTransientResponseDoesNotRetryWhenNotRetrySafe(): void
    {
        $pipeline = new ResiliencePipeline($this->fastOptions());
        $calls = 0;

        $result = $pipeline->execute(false, function () use (&$calls) {
            $calls++;
            return $this->response(503);
        });

        self::assertSame(503, $result->statusCode);
        self::assertSame(1, $calls);
    }

    public function testRetriesAreCappedAtMaxRetryAttempts(): void
    {
        $pipeline = new ResiliencePipeline($this->fastOptions());
        $calls = 0;

        $result = $pipeline->execute(true, function () use (&$calls) {
            $calls++;
            return $this->response(503);
        });

        self::assertSame(503, $result->statusCode);
        self::assertSame(3, $calls);
    }

    public function testNetworkErrorRetriesAndEventuallyRaisesTimeoutException(): void
    {
        $pipeline = new ResiliencePipeline($this->fastOptions());
        $calls = 0;

        try {
            $pipeline->execute(true, function () use (&$calls) {
                $calls++;
                throw new ConnectException('boom', new Request('GET', 'https://example.com'));
            });
            self::fail('Esperava CredicarroTimeoutException.');
        } catch (CredicarroTimeoutException $e) {
            self::assertSame(3, $calls);
            // A causa é sanitizada: sem o objeto de requisição/resposta do Guzzle.
            self::assertNotInstanceOf(ConnectException::class, $e->getPrevious());
            self::assertSame('ConnectException (código 0)', ($e->getPrevious())->getMessage());
        }
    }

    public function testNetworkErrorIsNotRetriedWhenNotRetrySafe(): void
    {
        $pipeline = new ResiliencePipeline($this->fastOptions());
        $calls = 0;

        try {
            $pipeline->execute(false, function () use (&$calls) {
                $calls++;
                throw new ConnectException('boom', new Request('POST', 'https://example.com'));
            });
            self::fail('Esperava CredicarroTimeoutException.');
        } catch (CredicarroTimeoutException $e) {
            self::assertSame(1, $calls);
        }
    }

    public function testNonNetworkExceptionsPropagateUntouched(): void
    {
        $pipeline = new ResiliencePipeline($this->fastOptions());

        $this->expectException(\DomainException::class);
        $pipeline->execute(true, function () {
            throw new \DomainException('erro de programação');
        });
    }

    public function testTotalTimeoutRaisesTimeoutException(): void
    {
        // Orçamento de 10 ms e uma tentativa que demora mais: sem tempo restante não há nova tentativa.
        $pipeline = new ResiliencePipeline(new ResilienceOptions(10, 10, 60000, 5, 1));
        $calls = 0;

        try {
            $pipeline->execute(true, function () use (&$calls) {
                $calls++;
                usleep(30000);
                throw new ConnectException('boom', new Request('GET', 'https://example.com'));
            });
            self::fail('Esperava CredicarroTimeoutException.');
        } catch (CredicarroTimeoutException $e) {
            self::assertSame(1, $calls);
        }
    }

    public function testCircuitBreakerOpensAfterFailureThresholdAndBlocksNextCall(): void
    {
        $options = new ResilienceOptions(5000, 1000, 60000, 1, 500, 0.5, 30000, 2, 60000);
        $pipeline = new ResiliencePipeline($options);

        for ($i = 0; $i < 2; $i++) {
            $pipeline->execute(false, function () {
                return $this->response(503);
            });
        }

        $this->expectException(CredicarroTimeoutException::class);
        $pipeline->execute(false, function () {
            return $this->response(200);
        });
    }

    public function testRetryAfterHeaderIsHonoured(): void
    {
        $pipeline = new ResiliencePipeline($this->fastOptions());
        $calls = 0;
        $started = microtime(true);

        $pipeline->execute(true, function () use (&$calls) {
            $calls++;
            return $calls === 1
                ? new ApiResponse(429, [], ['Retry-After' => '1'])
                : $this->response(200);
        });

        self::assertSame(2, $calls);
        self::assertGreaterThanOrEqual(0.9, microtime(true) - $started);
    }

    public function testRateLimitedResponseSurfacesAsRateLimitExceptionWithRetryAfter(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(429, ['tipo' => 'RateLimit'], ['Retry-After' => '0']),
            TestClientFactory::json(429, ['tipo' => 'RateLimit'], ['Retry-After' => '0']),
            TestClientFactory::json(429, ['tipo' => 'RateLimit'], ['Retry-After' => '7']),
        ]);

        try {
            $h->client->webhooks->list();
            self::fail('Esperava CredicarroRateLimitException.');
        } catch (CredicarroRateLimitException $e) {
            self::assertSame(7000, $e->getRetryAfterMs());
            self::assertCount(3, $h->apiRequests());
        }
    }

    public function testServerErrorIsRetriedThroughTheClientForGetRoutes(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(500, ['type' => 'about:blank', 'title' => 'Erro interno', 'status' => 500]),
            TestClientFactory::json(200, [
                'itens' => [],
                'pagina' => [
                    'tem_proximo' => false,
                    'tem_anterior' => false,
                    'numero' => 1,
                    'tamanho' => 10,
                    'total' => 0,
                ],
            ]),
        ]);

        $page = $h->client->webhooks->list();

        self::assertSame(0, $page->totalCount);
        self::assertCount(2, $h->apiRequests());
    }

    public function testAttemptThatFailsWithServerErrorAfterAllRetriesRaisesApiException(): void
    {
        $h = TestClientFactory::harness([
            new Response(503),
            new Response(503),
            new Response(503),
        ]);

        $this->expectException(CredicarroApiException::class);
        $h->client->webhooks->list();
    }
}
