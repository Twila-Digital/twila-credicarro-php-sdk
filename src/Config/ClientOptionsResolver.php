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

        if ($options->getBaseUrl() === null) {
            Environment::baseUrl($options->getEnvironment());
        } else {
            self::validateBaseUrl($options->getBaseUrl());
        }

        self::validateResilience($options->getResilience());
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
        if ($resilience->maxRetryAttempts < 1) {
            throw new CredicarroConfigurationException('resilience.maxRetryAttempts deve ser maior ou igual a 1.');
        }

        if ($resilience->totalTimeoutMs <= 0) {
            throw new CredicarroConfigurationException('resilience.totalTimeoutMs deve ser maior que zero.');
        }

        if ($resilience->attemptTimeoutMs <= 0) {
            throw new CredicarroConfigurationException('resilience.attemptTimeoutMs deve ser maior que zero.');
        }

        if ($resilience->documentUploadAttemptTimeoutMs <= 0) {
            throw new CredicarroConfigurationException(
                'resilience.documentUploadAttemptTimeoutMs deve ser maior que zero.'
            );
        }

        if ($resilience->attemptTimeoutMs > $resilience->totalTimeoutMs) {
            throw new CredicarroConfigurationException(
                'resilience.attemptTimeoutMs não pode ser maior que resilience.totalTimeoutMs.'
            );
        }

        if ($resilience->retryBaseDelayMs < 0) {
            throw new CredicarroConfigurationException('resilience.retryBaseDelayMs não pode ser negativo.');
        }

        if ($resilience->circuitBreakerFailureRatio <= 0 || $resilience->circuitBreakerFailureRatio > 1) {
            throw new CredicarroConfigurationException(
                'resilience.circuitBreakerFailureRatio deve estar entre 0 (exclusivo) e 1 (inclusivo).'
            );
        }

        if ($resilience->circuitBreakerMinimumThroughput < 2) {
            throw new CredicarroConfigurationException(
                'resilience.circuitBreakerMinimumThroughput deve ser maior ou igual a 2.'
            );
        }
    }
}
