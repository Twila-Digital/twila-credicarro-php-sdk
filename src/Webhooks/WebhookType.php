<?php

declare(strict_types=1);

namespace Twila\Credicarro\Webhooks;

use Twila\Credicarro\Internal\Mapping\EnumLookup;

/**
 * Evento notificado pelo webhook (`tipo`). Hoje só `PROPOSAL` envia notificações. O cadastro usa o número; a listagem
 * devolve o texto (`Customer`, `Simulation`, `Proposal`).
 */
final class WebhookType
{
    public const CUSTOMER = 1;
    public const SIMULATION = 2;
    public const PROPOSAL = 3;
    public const UNKNOWN = -1;

    private const NAMES = [
        self::CUSTOMER => 'Customer',
        self::SIMULATION => 'Simulation',
        self::PROPOSAL => 'Proposal',
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
