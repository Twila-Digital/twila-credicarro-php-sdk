<?php

declare(strict_types=1);

namespace Twila\Credicarro\Errors;

use Twila\Credicarro\Internal\Http\ApiResponse;
use Twila\Credicarro\Internal\Resilience\RetryAfter;

final class ExceptionFactory
{
    public static function fromResponse(ApiResponse $response): CredicarroException
    {
        $problem = ProblemDetails::parse($response->body);
        $rawBody = $response->rawBody === '' ? null : $response->rawBody;
        $message = self::buildMessage($response->statusCode, $problem);

        if ($response->statusCode === 401) {
            return new CredicarroAuthenticationException($message, null, 401, $problem);
        }

        if ($response->statusCode === 400) {
            return new CredicarroValidationException($message, $problem, $rawBody);
        }

        if ($response->statusCode === 422) {
            return new CredicarroBusinessRuleException($message, $problem, $rawBody);
        }

        if ($response->statusCode === 429) {
            return new CredicarroRateLimitException($message, $problem, self::retryAfterMs($response), $rawBody);
        }

        return new CredicarroApiException($message, $response->statusCode, $problem, $rawBody);
    }

    /**
     * Só status, `tipo` e `titulo`: o `detalhe` e o corpo podem conter dados pessoais.
     */
    private static function buildMessage(int $statusCode, ProblemDetails $problem): string
    {
        if ($statusCode === 401 && $problem->type === null && $problem->title === null) {
            return 'Não autorizado (401): o token de acesso está ausente, inválido ou expirado.';
        }

        $message = sprintf('A API do Credicarro respondeu %d', $statusCode);
        if ($problem->type !== null) {
            $message .= sprintf(' [%s]', $problem->type);
        }

        if ($problem->title !== null) {
            $message .= sprintf(' - %s', $problem->title);
        }

        return $message . '.';
    }

    private static function retryAfterMs(ApiResponse $response): ?int
    {
        return RetryAfter::milliseconds($response->header('retry-after'));
    }
}
