<?php

declare(strict_types=1);

namespace Twila\Credicarro\Webhooks;

/**
 * Webhook cadastrado. A credencial e o segredo de assinatura nunca são devolvidos pela API.
 */
final class Webhook
{
    /** @var string `id_webhook` */
    public $id;

    /** @var int um dos WebhookType::* */
    public $type;

    /** @var string|null `tipo` (texto original) */
    public $typeRaw;

    /** @var string `url` */
    public $url;

    /** @var int um dos WebhookAuthenticationType::* */
    public $authenticationType;

    /** @var string|null `tipo_autenticacao` (texto original) */
    public $authenticationTypeRaw;

    /** @var int um dos WebhookStatus::* */
    public $status;

    /** @var string|null `status` (texto original) */
    public $statusRaw;

    public function __construct(
        string $id,
        int $type,
        ?string $typeRaw,
        string $url,
        int $authenticationType,
        ?string $authenticationTypeRaw,
        int $status,
        ?string $statusRaw
    ) {
        $this->id = $id;
        $this->type = $type;
        $this->typeRaw = $typeRaw;
        $this->url = $url;
        $this->authenticationType = $authenticationType;
        $this->authenticationTypeRaw = $authenticationTypeRaw;
        $this->status = $status;
        $this->statusRaw = $statusRaw;
    }
}
