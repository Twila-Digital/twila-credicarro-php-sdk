<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal;

use Twila\Credicarro\Errors\CredicarroValidationException;
use Twila\Credicarro\Errors\ProblemDetails;

/**
 * Validações locais, antes de qualquer chamada de rede. Falham com os mesmos códigos `Validation.*` da API.
 *
 * @internal
 */
final class Validation
{
    public static function fail(string $code, string $message, ?string $field = null): CredicarroValidationException
    {
        $problem = new ProblemDetails($code, 'Requisição inválida', 400, $message, $field);

        return new CredicarroValidationException(
            sprintf('Validação local do SDK [%s] - Requisição inválida.', $code),
            $problem
        );
    }

    public static function requireUuid(string $value, string $field): string
    {
        $pattern = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';
        if (preg_match($pattern, $value) !== 1) {
            throw self::fail(
                'Validation.Identifier.Invalid',
                sprintf("O identificador informado em '%s' deve ser um UUID válido.", $field),
                $field
            );
        }

        return $value;
    }

    public static function requireNotBlank(?string $value, string $field): string
    {
        if ($value === null || trim($value) === '') {
            throw self::fail(
                'Validation.Field.Required',
                sprintf("O campo '%s' é obrigatório.", $field),
                $field
            );
        }

        return $value;
    }

    public static function requireIdempotencyKey(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        // 1 a 200 caracteres ASCII imprimíveis (0x20-0x7E), não só espaços. Vazia nunca vale como "sem chave".
        if (
            strlen($key) > 200
            || trim($key) === ''
            || preg_match('/^[\x20-\x7E]{1,200}$/D', $key) !== 1
        ) {
            throw self::fail(
                'Validation.IdempotencyKey.Invalid',
                'A Idempotency-Key deve ter de 1 a 200 caracteres ASCII imprimíveis, e não só espaços.',
                'Idempotency-Key'
            );
        }

        return $key;
    }
}
