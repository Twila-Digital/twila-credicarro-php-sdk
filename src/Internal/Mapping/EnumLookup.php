<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Mapping;

/**
 * Conversão código/nome <-> constante dos "enums" do SDK. Valores desconhecidos viram -1 (`UNKNOWN`); o valor bruto
 * é preservado nos campos `*Raw`/`*Name` dos modelos.
 *
 * @internal
 */
final class EnumLookup
{
    public const UNKNOWN = -1;

    /**
     * @param array<int, string> $names código => nome
     */
    public static function fromValue(array $names, int $value): int
    {
        return array_key_exists($value, $names) ? $value : self::UNKNOWN;
    }

    /**
     * Compara sem diferenciar maiúsculas de minúsculas, em todas as tabelas informadas (por exemplo, nomes em
     * português do payload v2 e em inglês do payload v1).
     *
     * @param array<int, string> ...$tables código => nome
     */
    public static function fromName(?string $name, array ...$tables): int
    {
        if ($name === null || $name === '') {
            return self::UNKNOWN;
        }

        foreach ($tables as $table) {
            foreach ($table as $code => $candidate) {
                if (strcasecmp($candidate, $name) === 0) {
                    return $code;
                }
            }
        }

        return self::UNKNOWN;
    }

    /**
     * @param array<int, string> $names código => nome
     */
    public static function name(array $names, int $value): ?string
    {
        return $names[$value] ?? null;
    }
}
