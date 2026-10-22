<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

use Twila\Credicarro\Internal\Mapping\EnumLookup;

/**
 * Modalidade da proposta (`tipo`: `Financing` ou `Refinancing`).
 */
final class ProposalType
{
    public const FINANCING = 1;
    public const REFINANCING = 2;
    public const UNKNOWN = -1;

    private const NAMES = [
        self::FINANCING => 'Financing',
        self::REFINANCING => 'Refinancing',
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
