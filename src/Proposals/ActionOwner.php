<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

use Twila\Credicarro\Internal\Mapping\EnumLookup;

/**
 * Quem precisa agir na etapa (`enum_responsavel_acao` / `responsavel_acao`). `INTEGRATOR` indica que o seu sistema
 * precisa enviar algo pela API.
 */
final class ActionOwner
{
    public const NONE = 0;
    public const CUSTOMER = 1;
    public const INTEGRATOR = 2;
    public const CREDICARRO = 3;
    public const UNKNOWN = -1;

    private const NAMES = [
        self::NONE => 'NENHUM',
        self::CUSTOMER => 'CLIENTE',
        self::INTEGRATOR => 'INTEGRADOR',
        self::CREDICARRO => 'CREDICARRO',
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
