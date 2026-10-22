<?php

declare(strict_types=1);

namespace Twila\Credicarro\Errors;

/**
 * `429` — limite de requisições excedido.
 */
final class CredicarroRateLimitException extends CredicarroApiException
{
    /** @var int|null */
    private $retryAfterMs;

    public function __construct(
        string $message,
        ProblemDetails $problemDetails,
        ?int $retryAfterMs = null,
        ?string $rawBody = null
    ) {
        parent::__construct($message, 429, $problemDetails, $rawBody);
        $this->retryAfterMs = $retryAfterMs;
    }

    /**
     * Espera sugerida pelo cabeçalho `Retry-After`, em milissegundos.
     */
    public function getRetryAfterMs(): ?int
    {
        return $this->retryAfterMs;
    }
}
