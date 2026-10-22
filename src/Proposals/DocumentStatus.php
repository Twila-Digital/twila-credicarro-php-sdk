<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

use Twila\Credicarro\Internal\Mapping\EnumLookup;

/**
 * Status de um documento na consulta da proposta. `PENDING` indica documento exigido ainda não enviado.
 */
final class DocumentStatus
{
    public const RECEIVED = 0;
    public const VALID = 1;
    public const INVALID = 2;
    public const IN_ANALYSIS = 3;
    public const DELETED = 4;
    public const PENDING = 5;
    public const UNKNOWN = -1;

    private const NAMES = [
        self::RECEIVED => 'RECEBIDO',
        self::VALID => 'VALIDO',
        self::INVALID => 'INVALIDO',
        self::IN_ANALYSIS => 'EM_ANALISE',
        self::DELETED => 'EXCLUIDO',
        self::PENDING => 'PENDENTE',
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
