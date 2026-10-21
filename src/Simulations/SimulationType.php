<?php

declare(strict_types=1);

namespace Twila\Credicarro\Simulations;

use Twila\Credicarro\Internal\Mapping\EnumLookup;

/**
 * Tipo da simulação (`tipo`): `Financing`, `Refinancing` ou `AmountSimulation`.
 */
final class SimulationType
{
    public const FINANCING = 1;
    public const REFINANCING = 2;
    public const AMOUNT_SIMULATION = 3;
    public const UNKNOWN = -1;

    private const NAMES = [
        self::FINANCING => 'Financing',
        self::REFINANCING => 'Refinancing',
        self::AMOUNT_SIMULATION => 'AmountSimulation',
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
