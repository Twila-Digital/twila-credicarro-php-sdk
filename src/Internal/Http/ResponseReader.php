<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Http;

use Twila\Credicarro\Errors\CredicarroApiException;
use Twila\Credicarro\Errors\ProblemDetails;
use Twila\Credicarro\Internal\Mapping\Wire;

/**
 * Leitura do corpo de respostas de sucesso, falhando com CredicarroApiException se o formato for inesperado.
 *
 * @internal
 */
final class ResponseReader
{
    /**
     * @return array<mixed>
     */
    public static function object(ApiResponse $response): array
    {
        if (!is_array($response->body)) {
            throw self::unexpected($response);
        }

        return $response->body;
    }

    public static function requiredString(ApiResponse $response, string $field): string
    {
        $value = Wire::nullableString(self::object($response), $field);
        if ($value === null || $value === '') {
            throw self::unexpected($response);
        }

        return $value;
    }

    private static function unexpected(ApiResponse $response): CredicarroApiException
    {
        return new CredicarroApiException(
            sprintf('A API do Credicarro respondeu %d com um corpo em formato inesperado.', $response->statusCode),
            $response->statusCode,
            new ProblemDetails(),
            $response->rawBody === '' ? null : $response->rawBody
        );
    }
}
