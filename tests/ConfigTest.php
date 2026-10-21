<?php

declare(strict_types=1);

namespace Twila\Credicarro\Tests;

use PHPUnit\Framework\TestCase;
use Twila\Credicarro\Config\ClientOptions;
use Twila\Credicarro\Config\ClientOptionsResolver;
use Twila\Credicarro\Config\Environment;
use Twila\Credicarro\Config\ResilienceOptions;
use Twila\Credicarro\Errors\CredicarroConfigurationException;

final class ConfigTest extends TestCase
{
    public function testResolveDefaultsToProductionBaseUrl(): void
    {
        $resolved = ClientOptionsResolver::resolve(new ClientOptions('id', 'secret'));

        self::assertSame('https://api.credicarro.com.br/integration/', $resolved->baseUrl);
    }

    public function testStagingEnvironmentBaseUrl(): void
    {
        $resolved = ClientOptionsResolver::resolve(new ClientOptions('id', 'secret', Environment::STAGING));

        self::assertSame('https://api.staging.credicarro.com.br/integration/', $resolved->baseUrl);
    }

    public function testResolveNormalizesBaseUrlWithoutTrailingSlash(): void
    {
        $resolved = ClientOptionsResolver::resolve(
            new ClientOptions('id', 'secret', Environment::PRODUCTION, 'https://example.com/integration')
        );

        self::assertSame('https://example.com/integration/', $resolved->baseUrl);
    }

    /**
     * @dataProvider blankCredentialsProvider
     */
    public function testBlankCredentialsRaiseConfigurationException(string $clientId, string $clientSecret): void
    {
        $this->expectException(CredicarroConfigurationException::class);
        ClientOptionsResolver::resolve(new ClientOptions($clientId, $clientSecret));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function blankCredentialsProvider(): array
    {
        return [
            'blank client id' => ['', 'secret'],
            'blank client secret' => ['id', ''],
            'whitespace client id' => ['   ', 'secret'],
        ];
    }

    /**
     * @dataProvider insecureBaseUrlProvider
     */
    public function testNonHttpsBaseUrlIsRejected(string $baseUrl): void
    {
        $this->expectException(CredicarroConfigurationException::class);
        ClientOptionsResolver::resolve(new ClientOptions('id', 'secret', Environment::PRODUCTION, $baseUrl));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function insecureBaseUrlProvider(): array
    {
        return [
            'not a url' => ['not-a-url'],
            'plain http remote' => ['http://api.credicarro.com.br/integration/'],
            'ftp' => ['ftp://example.com/'],
            'localhost lookalike' => ['http://localhost.evil.com/'],
        ];
    }

    /**
     * @dataProvider loopbackBaseUrlProvider
     */
    public function testHttpIsAcceptedOnlyForLoopback(string $baseUrl): void
    {
        $resolved = ClientOptionsResolver::resolve(
            new ClientOptions('id', 'secret', Environment::PRODUCTION, $baseUrl)
        );

        self::assertSame(rtrim($baseUrl, '/') . '/', $resolved->baseUrl);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function loopbackBaseUrlProvider(): array
    {
        return [
            'localhost' => ['http://localhost:8080/integration'],
            'loopback ip' => ['http://127.0.0.1:5000/integration/'],
        ];
    }

    public function testInvalidEnvironmentRaisesConfigurationException(): void
    {
        $this->expectException(CredicarroConfigurationException::class);
        ClientOptionsResolver::resolve(new ClientOptions('id', 'secret', 'sandbox'));
    }

    public function testResilienceDefaultsFollowTheContract(): void
    {
        $resilience = new ResilienceOptions();

        self::assertSame(30000, $resilience->totalTimeoutMs);
        self::assertSame(10000, $resilience->attemptTimeoutMs);
        self::assertSame(60000, $resilience->documentUploadAttemptTimeoutMs);
        self::assertSame(3, $resilience->maxRetryAttempts);
        self::assertSame(500, $resilience->retryBaseDelayMs);
        self::assertSame(0.5, $resilience->circuitBreakerFailureRatio);
        self::assertSame(30000, $resilience->circuitBreakerSamplingDurationMs);
        self::assertSame(10, $resilience->circuitBreakerMinimumThroughput);
        self::assertSame(15000, $resilience->circuitBreakerBreakDurationMs);
        self::assertTrue($resilience->retryOn500);
        self::assertFalse($resilience->disableAutomaticIdempotencyKey);
    }

    /**
     * @dataProvider invalidResilienceOptionsProvider
     */
    public function testInvalidResilienceOptionsRaiseConfigurationException(ResilienceOptions $resilience): void
    {
        $this->expectException(CredicarroConfigurationException::class);
        ClientOptionsResolver::resolve(new ClientOptions('id', 'secret', Environment::PRODUCTION, null, $resilience));
    }

    /**
     * @return array<string, array{0: ResilienceOptions}>
     */
    public static function invalidResilienceOptionsProvider(): array
    {
        return [
            'zero retries' => [new ResilienceOptions(30000, 10000, 60000, 0)],
            'zero total timeout' => [new ResilienceOptions(0)],
            'zero attempt timeout' => [new ResilienceOptions(30000, 0)],
            'zero document timeout' => [new ResilienceOptions(30000, 10000, 0)],
            'attempt timeout above total' => [new ResilienceOptions(10000, 20000)],
            'failure ratio zero' => [new ResilienceOptions(30000, 10000, 60000, 3, 500, 0.0)],
            'failure ratio above 1' => [new ResilienceOptions(30000, 10000, 60000, 3, 500, 1.5)],
            'minimum throughput below 2' => [new ResilienceOptions(30000, 10000, 60000, 3, 500, 0.5, 30000, 1)],
        ];
    }

    public function testClientSecretNeverAppearsInDebugOutput(): void
    {
        $options = new ClientOptions('id-publico', 'super-segredo-123');
        $resolved = ClientOptionsResolver::resolve($options);

        self::assertStringNotContainsString('super-segredo-123', print_r($options, true));
        self::assertStringNotContainsString('super-segredo-123', print_r($resolved, true));
    }
}
