<?php

declare(strict_types=1);

namespace Twila\Credicarro\Errors;

/**
 * `400` — dados inválidos (formato, campo obrigatório ausente). Corrija o corpo antes de repetir. Também é lançada
 * pelas validações locais do SDK (paginação, arquivo de documento, identificadores), com os códigos `Validation.*`.
 */
final class CredicarroValidationException extends CredicarroApiException
{
    public function __construct(string $message, ProblemDetails $problemDetails, ?string $rawBody = null)
    {
        parent::__construct($message, 400, $problemDetails, $rawBody);
    }
}
