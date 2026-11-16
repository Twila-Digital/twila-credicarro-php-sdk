<?php

declare(strict_types=1);

namespace Twila\Credicarro\Webhooks;

use Twila\Credicarro\Internal\Masking\MasksSensitiveData;

/**
 * Envio de webhook registrado no histórico. Use `id` (`id_auditoria`) em `WebhooksClient::resend`.
 */
final class WebhookDelivery
{
    use MasksSensitiveData;

    /** @var string `id_auditoria` */
    public $id;

    /** @var string|null `id_webhook` */
    public $webhookId;

    /** @var int um dos WebhookType::* */
    public $type;

    /** @var string|null `tipo` (texto original) */
    public $typeRaw;

    /** @var string corpo enviado, como texto JSON (`requisicao`) */
    public $request;

    /** @var string corpo devolvido pela sua URL; vazio quando não houve resposta (`resposta`) */
    public $response;

    /** @var int|null status HTTP devolvido pela sua URL; `null` quando ela não respondeu (`status_http`) */
    public $statusCode;

    /** @var string data e hora do envio (`data_criacao`) */
    public $createdAt;

    public function __construct(
        string $id,
        ?string $webhookId,
        int $type,
        ?string $typeRaw,
        string $request,
        string $response,
        ?int $statusCode,
        string $createdAt
    ) {
        $this->id = $id;
        $this->webhookId = $webhookId;
        $this->type = $type;
        $this->typeRaw = $typeRaw;
        $this->request = $request;
        $this->response = $response;
        $this->statusCode = $statusCode;
        $this->createdAt = $createdAt;
    }

    /**
     * @return array<string, string>
     */
    protected function sensitiveFields(): array
    {
        return [
            'request' => 'full',
            'response' => 'full',
        ];
    }
}
