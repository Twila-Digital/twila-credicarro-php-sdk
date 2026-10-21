<?php

declare(strict_types=1);

namespace Twila\Credicarro\Webhooks;

use Twila\Credicarro\Internal\Masking\MasksSensitiveData;

/**
 * Resultado do cadastro. O `signingSecret` só é devolvido nesta resposta: grave-o em um cofre de segredos.
 */
final class CreateWebhookResult
{
    use MasksSensitiveData;

    /** @var string `id_webhook` */
    public $webhookId;

    /** @var string `segredo_assinatura` — usado para validar o header `X-CrediCarro-Signature` */
    public $signingSecret;

    public function __construct(
        string $webhookId,
        #[\SensitiveParameter]
        string $signingSecret
    ) {
        $this->webhookId = $webhookId;
        $this->signingSecret = $signingSecret;
    }

    /**
     * @return array<string, string>
     */
    protected function sensitiveFields(): array
    {
        return [
            'signingSecret' => 'full',
        ];
    }
}
