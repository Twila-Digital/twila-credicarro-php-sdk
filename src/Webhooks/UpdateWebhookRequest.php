<?php

declare(strict_types=1);

namespace Twila\Credicarro\Webhooks;

/**
 * A autenticação (tipo e credencial) não pode ser alterada: para trocá-la, exclua o webhook e cadastre outro.
 */
final class UpdateWebhookRequest
{
    /** @var int um dos WebhookType::* (`tipo`) */
    public $type;

    /** @var string URL HTTPS pública (`url`) */
    public $url;

    /** @var int|null um dos WebhookStatus::* (`status`); quando omitido, mantém o status atual */
    public $status;

    public function __construct(int $type, string $url, ?int $status = null)
    {
        $this->type = $type;
        $this->url = $url;
        $this->status = $status;
    }
}
