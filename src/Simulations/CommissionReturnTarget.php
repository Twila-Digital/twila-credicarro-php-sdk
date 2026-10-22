<?php

declare(strict_types=1);

namespace Twila\Credicarro\Simulations;

use Twila\Credicarro\Internal\Mapping\EnumLookup;

/**
 * A quem pertence a configuração de comissão (`alvo`): `Store` ou `Organization`.
 */
final class CommissionReturnTarget
{
    public const STORE = 1;
    public const ORGANIZATION = 2;
    public const UNKNOWN = -1;

    private const NAMES = [
        self::STORE => 'Store',
        self::ORGANIZATION => 'Organization',
    ];

    private function __construct()
    {
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
