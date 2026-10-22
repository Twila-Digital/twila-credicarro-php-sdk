<?php

declare(strict_types=1);

namespace Twila\Credicarro\Config;

use Twila\Credicarro\Errors\CredicarroConfigurationException;

final class Environment
{
    public const STAGING = 'staging';
    public const PRODUCTION = 'production';

    private const BASE_URLS = [
        self::STAGING => 'https://api.staging.credicarro.com.br/integration/',
        self::PRODUCTION => 'https://api.credicarro.com.br/integration/',
    ];

    public static function baseUrl(string $environment): string
    {
        if (!isset(self::BASE_URLS[$environment])) {
            throw new CredicarroConfigurationException(
                sprintf("Ambiente inválido: '%s'. Use Environment::STAGING ou Environment::PRODUCTION.", $environment)
            );
        }

        return self::BASE_URLS[$environment];
    }
}
