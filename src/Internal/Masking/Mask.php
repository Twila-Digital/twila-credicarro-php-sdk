<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Masking;

/**
 * Máscaras para dados pessoais e segredos em dumps, JSON e serialização.
 *
 * @internal
 */
final class Mask
{
    public const FULL = '***';

    /**
     * CPF `12345678909` ou `123.456.789-09` vira `***.456.789-**`; qualquer outro formato vira `***`.
     *
     * @param mixed $value
     */
    public static function cpf($value): string
    {
        if (!is_string($value)) {
            return self::FULL;
        }

        $digits = (string) preg_replace('/\D+/', '', $value);
        if (strlen($digits) !== 11) {
            return self::FULL;
        }

        return '***.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-**';
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public static function full($value)
    {
        return $value === null ? null : self::FULL;
    }
}
