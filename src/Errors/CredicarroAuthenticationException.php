<?php

declare(strict_types=1);

namespace Twila\Credicarro\Errors;

/**
 * Falha ao gerar o token de acesso (credenciais inválidas, requisição malformada) ou token rejeitado mesmo após a
 * renovação. A mensagem nunca inclui o clientSecret nem o token.
 */
final class CredicarroAuthenticationException extends CredicarroException
{
    /** @var int|null */
    private $statusCode;

    /** @var ProblemDetails|null */
    private $problemDetails;

    public function __construct(
        string $message,
        ?\Throwable $previous = null,
        ?int $statusCode = null,
        ?ProblemDetails $problemDetails = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
        $this->problemDetails = $problemDetails;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getProblemDetails(): ?ProblemDetails
    {
        return $this->problemDetails;
    }

    /**
     * Código estável do erro (campo `tipo`), por exemplo `Integration.Authentication.InvalidCredentials`.
     */
    public function getErrorCode(): ?string
    {
        return $this->problemDetails !== null ? $this->problemDetails->type : null;
    }
}
