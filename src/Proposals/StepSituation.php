<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

use Twila\Credicarro\Internal\Mapping\EnumLookup;

/**
 * Situação da etapa (`enum_situacao` / `situacao`).
 */
final class StepSituation
{
    public const AWAITING_SUBMISSION = 1;
    public const AWAITING_CUSTOMER = 2;
    public const PROCESSING = 3;
    public const PENDING_CORRECTION = 4;
    public const BLOCKED = 5;
    public const COMPLETED = 6;
    public const CANCELED = 7;
    public const UNKNOWN = -1;

    private const NAMES = [
        self::AWAITING_SUBMISSION => 'AGUARDANDO_ENVIO',
        self::AWAITING_CUSTOMER => 'AGUARDANDO_CLIENTE',
        self::PROCESSING => 'EM_PROCESSAMENTO',
        self::PENDING_CORRECTION => 'PENDENTE_CORRECAO',
        self::BLOCKED => 'BLOQUEADA',
        self::COMPLETED => 'CONCLUIDA',
        self::CANCELED => 'CANCELADA',
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
