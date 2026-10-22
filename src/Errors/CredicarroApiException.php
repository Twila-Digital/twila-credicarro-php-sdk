<?php

declare(strict_types=1);

namespace Twila\Credicarro\Errors;

/**
 * Resposta de erro da API. A mensagem traz só o status, o `tipo` e o `titulo`; o `detalhe` e o corpo bruto ficam em
 * propriedades porque podem conter dados pessoais — não os envie para logs sem tratamento.
 */
class CredicarroApiException extends CredicarroException
{
    /** @var int */
    private $statusCode;

    /** @var ProblemDetails */
    private $problemDetails;

    /** @var string|null */
    private $rawBody;

    public function __construct(
        string $message,
        int $statusCode,
        ProblemDetails $problemDetails,
        ?string $rawBody = null
    ) {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->problemDetails = $problemDetails;
        $this->rawBody = $rawBody;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getProblemDetails(): ProblemDetails
    {
        return $this->problemDetails;
    }

    /**
     * Código estável do erro (campo `tipo`, ou `type` no formato padrão). Use-o no seu tratamento.
     */
    public function getErrorCode(): ?string
    {
        return $this->problemDetails->type;
    }

    public function getTitle(): ?string
    {
        return $this->problemDetails->title;
    }

    /**
     * Mensagem para logs e suporte. Pode conter dados pessoais e mudar sem aviso.
     */
    public function getDetail(): ?string
    {
        return $this->problemDetails->detail;
    }

    public function getField(): ?string
    {
        return $this->problemDetails->field;
    }

    /**
     * Itens da lista `erros` do corpo (`codigo`, `mensagem`, `campo`), presente só quando o JSON enviado está
     * malformado ou tem um valor de tipo errado. Vazia nos demais casos: para erros comuns use `getErrorCode()` e
     * `getField()`.
     *
     * @return array<int, ProblemError>
     */
    public function getErrors(): array
    {
        return $this->problemDetails->errors;
    }

    /**
     * Corpo da resposta exatamente como recebido. Pode conter dados pessoais.
     */
    public function getRawBody(): ?string
    {
        return $this->rawBody;
    }
}
