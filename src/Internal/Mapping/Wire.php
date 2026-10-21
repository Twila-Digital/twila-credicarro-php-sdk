<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Mapping;

/**
 * Leitura tolerante dos objetos JSON da API. Campos ausentes viram valores neutros (ou null nos opcionais), de modo que
 * um campo novo ou removido pela API não derruba o SDK.
 *
 * @internal
 */
final class Wire
{
    /** @var array<string, bool>|null */
    private static $recorded;

    /**
     * Uso exclusivo dos contract tests: passa a registrar quais chaves os mappers leem.
     */
    public static function startRecording(): void
    {
        self::$recorded = [];
    }

    /**
     * @return array<int, string>
     */
    public static function stopRecording(): array
    {
        $keys = array_map('strval', array_keys(self::$recorded ?? []));
        self::$recorded = null;

        return $keys;
    }

    /**
     * @param array<mixed> $wire
     */
    public static function has(array $wire, string $key): bool
    {
        self::touch($key);

        return array_key_exists($key, $wire);
    }

    /**
     * @param array<mixed> $wire
     */
    public static function string(array $wire, string $key, string $default = ''): string
    {
        return self::nullableString($wire, $key) ?? $default;
    }

    /**
     * @param array<mixed> $wire
     */
    public static function nullableString(array $wire, string $key): ?string
    {
        self::touch($key);

        $value = $wire[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param array<mixed> $wire
     */
    public static function int(array $wire, string $key, int $default = 0): int
    {
        return self::nullableInt($wire, $key) ?? $default;
    }

    /**
     * @param array<mixed> $wire
     */
    public static function nullableInt(array $wire, string $key): ?int
    {
        self::touch($key);

        $value = $wire[$key] ?? null;

        return is_int($value) || (is_string($value) && is_numeric($value)) || is_float($value)
            ? (int) $value
            : null;
    }

    /**
     * @param array<mixed> $wire
     */
    public static function float(array $wire, string $key, float $default = 0.0): float
    {
        return self::nullableFloat($wire, $key) ?? $default;
    }

    /**
     * @param array<mixed> $wire
     */
    public static function nullableFloat(array $wire, string $key): ?float
    {
        self::touch($key);

        $value = $wire[$key] ?? null;

        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))
            ? (float) $value
            : null;
    }

    /**
     * @param array<mixed> $wire
     */
    public static function bool(array $wire, string $key, bool $default = false): bool
    {
        return self::nullableBool($wire, $key) ?? $default;
    }

    /**
     * @param array<mixed> $wire
     */
    public static function nullableBool(array $wire, string $key): ?bool
    {
        self::touch($key);

        $value = $wire[$key] ?? null;

        return is_bool($value) ? $value : null;
    }

    /**
     * Sub-objeto JSON (null quando ausente, nulo ou não for um objeto).
     *
     * @param array<mixed> $wire
     * @return array<mixed>|null
     */
    public static function object(array $wire, string $key): ?array
    {
        self::touch($key);

        $value = $wire[$key] ?? null;

        return is_array($value) ? $value : null;
    }

    /**
     * Lista de objetos JSON; itens que não forem objetos são descartados.
     *
     * @param array<mixed> $wire
     * @return array<int, array<mixed>>
     */
    public static function objects(array $wire, string $key): array
    {
        self::touch($key);

        $value = $wire[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Remove as chaves com valor null (campos opcionais não informados não são enviados).
     *
     * @param array<string, mixed> $wire
     * @return array<string, mixed>
     */
    public static function withoutNulls(array $wire): array
    {
        return array_filter($wire, static function ($value): bool {
            return $value !== null;
        });
    }

    private static function touch(string $key): void
    {
        if (self::$recorded !== null) {
            self::$recorded[$key] = true;
        }
    }
}
