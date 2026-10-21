<?php

declare(strict_types=1);

namespace Twila\Credicarro\Webhooks;

use Twila\Credicarro\Internal\Mapping\EnumLookup;

/**
 * Status do webhook (`status`). A edição usa o número; a listagem devolve o texto (`Active`, `Inactive`).
 */
final class WebhookStatus
{
    public const ACTIVE = 1;
    public const INACTIVE = 2;
    public const UNKNOWN = -1;

    private const NAMES = [
        self::ACTIVE => 'Active',
        self::INACTIVE => 'Inactive',
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
