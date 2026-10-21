<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

use Twila\Credicarro\Internal\Mapping\EnumLookup;

/**
 * Situação da biometria do participante (`enum_biometria` / `biometria`).
 */
final class BiometryStatus
{
    public const NOT_STARTED = 0;
    public const SESSION_CREATED = 1;
    public const IN_ANALYSIS = 2;
    public const APPROVED = 3;
    public const REJECTED = 4;
    public const UNKNOWN = -1;

    private const NAMES = [
        self::NOT_STARTED => 'NAO_INICIADA',
        self::SESSION_CREATED => 'SESSAO_CRIADA',
        self::IN_ANALYSIS => 'EM_ANALISE',
        self::APPROVED => 'APROVADA',
        self::REJECTED => 'REPROVADA',
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
