<?php

declare(strict_types=1);

namespace Twila\Credicarro\Config;

use Twila\Credicarro\Errors\CredicarroConfigurationException;

final class ClientOptionsResolver
{
    public static function resolve(ClientOptions $options): ResolvedClientOptions
    {
        self::validate($options);

        $raw = $options->getBaseUrl() ?? Environment::baseUrl($options->getEnvironment());
        $baseUrl = substr($raw, -1) === '/' ? $raw : $raw . '/';

        return new ResolvedClientOptions(
            $options->credentials(),
            $options->getEnvironment(),
            $baseUrl,
            $options->getResilience(),
            $options->getTokenCache()
        );
    }

    private static function validate(ClientOptions $options): void
    {
        $credentials = $options->credentials();

        if ($credentials->isClientIdBlank()) {
            throw new CredicarroConfigurationException('clientId é obrigatório.');
        }

        if ($credentials->isClientSecretBlank()) {
            throw new CredicarroConfigurationException('clientSecret é obrigatório.');
        }

        self::validateEndpoint($options);
        self::validateResilience($options->getResilience());
    }

    private static function validateEndpoint(ClientOptions $options): void
    {
        $baseUrl = $options->getBaseUrl();
        if ($baseUrl !== null) {
            self::validateBaseUrl($baseUrl);

            return;
        }

        Environment::baseUrl($options->getEnvironment());
    }

    private static function validateBaseUrl(string $baseUrl): void
    {
        $parts = parse_url($baseUrl);
        if ($parts === false || !isset($parts['scheme'], $parts['host']) || $parts['host'] === '') {
            throw new CredicarroConfigurationException('baseUrl, quando informada, deve ser uma URL absoluta válida.');
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $isLoopback = $host === 'localhost' || $host === '127.0.0.1';

        if ($scheme === 'https') {
            return;
        }

        if ($scheme === 'http' && $isLoopback) {
            return;
        }

        throw new CredicarroConfigurationException(
            'baseUrl deve usar https (http só é aceito para localhost e 127.0.0.1, em testes).'
        );
    }

    private static function validateResilience(ResilienceOptions $resilience): void
    {
        self::requireThat(
            $resilience->maxRetryAttempts >= 1,
            'resilience.maxRetryAttempts deve ser maior ou igual a 1.'
        );
        self::requireThat($resilience->totalTimeoutMs > 0, 'resilience.totalTimeoutMs deve ser maior que zero.');
        self::requireThat($resilience->attemptTimeoutMs > 0, 'resilience.attemptTimeoutMs deve ser maior que zero.');
        self::requireThat(
            $resilience->documentUploadAttemptTimeoutMs > 0,
            'resilience.documentUploadAttemptTimeoutMs deve ser maior que zero.'
        );
        self::requireThat(
            $resilience->attemptTimeoutMs <= $resilience->totalTimeoutMs,
            'resilience.attemptTimeoutMs não pode ser maior que resilience.totalTimeoutMs.'
        );
        self::requireThat($resilience->retryBaseDelayMs >= 0, 'resilience.retryBaseDelayMs não pode ser negativo.');
        self::requireThat(
            $resilience->circuitBreakerFailureRatio > 0 && $resilience->circuitBreakerFailureRatio <= 1,
            'resilience.circuitBreakerFailureRatio deve estar entre 0 (exclusivo) e 1 (inclusivo).'
        );
        self::requireThat(
            $resilience->circuitBreakerMinimumThroughput >= 2,
            'resilience.circuitBreakerMinimumThroughput deve ser maior ou igual a 2.'
        );
    }

    private static function requireThat(bool $valid, string $message): void
    {
        if (!$valid) {
            throw new CredicarroConfigurationException($message);
        }
    }
}
