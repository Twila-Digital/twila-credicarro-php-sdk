<?php

declare(strict_types=1);

namespace Twila\Credicarro\Errors;

/**
 * Falha de rede, tempo limite (da tentativa ou total) ou circuit breaker aberto.
 */
final class CredicarroTimeoutException extends CredicarroException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
