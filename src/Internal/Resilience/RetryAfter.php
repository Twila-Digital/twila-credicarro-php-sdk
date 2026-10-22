<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Resilience;

/**
 * Leitura do cabeçalho `Retry-After` (segundos ou data HTTP).
 *
 * @internal
 */
final class RetryAfter
{
    /** Teto, em segundos, aplicado antes de qualquer conversão para duração (valores absurdos são limitados). */
    public const MAX_SECONDS = 86400;

    /**
     * @return float|null segundos de espera; null quando ausente, inválido ou menor ou igual a zero
     */
    public static function seconds(?string $header, ?int $now = null): ?float
    {
        if ($header === null) {
            return null;
        }

        $value = trim($header);
        if ($value === '') {
            return null;
        }

        $seconds = is_numeric($value) ? (float) $value : self::secondsUntil($value, $now);
        if ($seconds === null || is_nan($seconds) || $seconds <= 0) {
            return null;
        }

        return min($seconds, (float) self::MAX_SECONDS);
    }

    /**
     * Segundos até a data HTTP do cabeçalho (forma `Retry-After: <data>`); null se a data for inválida.
     */
    private static function secondsUntil(string $date, ?int $now): ?float
    {
        $timestamp = strtotime($date);

        return $timestamp === false ? null : (float) ($timestamp - ($now ?? time()));
    }

    public static function milliseconds(?string $header, ?int $now = null): ?int
    {
        $seconds = self::seconds($header, $now);

        return $seconds === null ? null : (int) round($seconds * 1000);
    }
}
