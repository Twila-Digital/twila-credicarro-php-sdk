<?php

declare(strict_types=1);

namespace Twila\Credicarro\Webhooks;

use Twila\Credicarro\Internal\Mapping\EnumLookup;

/**
 * Autenticação que a Credicarro envia ao chamar a sua URL (`tipo_autenticacao`). Não pode ser alterada depois do
 * cadastro. A listagem devolve o texto (`None`, `Basic`, `Jwt`).
 */
final class WebhookAuthenticationType
{
    public const NONE = 1;
    public const BASIC = 2;
    public const JWT = 3;
    public const UNKNOWN = -1;

    private const NAMES = [
        self::NONE => 'None',
        self::BASIC => 'Basic',
        self::JWT => 'Jwt',
    ];

    private function __construct()
    {
    }

    public static function fromWireValue(int $value): int
    {
        return EnumLookup::fromValue(self::NAMES, $value);
    }

    public static function fromWireName(?string $name): int
    {
        return EnumLookup::fromName($name, self::NAMES);
    }

    public static function wireName(int $value): ?string
    {
        return EnumLookup::name(self::NAMES, $value);
    }
}
