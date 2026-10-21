<?php

declare(strict_types=1);

namespace Twila\Credicarro\Webhooks;

use Twila\Credicarro\Internal\Masking\MasksSensitiveData;

final class CreateWebhookRequest
{
    use MasksSensitiveData;

    /** @var int um dos WebhookType::* (`tipo`) */
    public $type;

    /** @var string URL HTTPS pública, até 2048 caracteres (`url`) */
    public $url;

    /** @var int um dos WebhookAuthenticationType::* (`tipo_autenticacao`), imutável depois do cadastro */
    public $authenticationType;

    /**
     * Credencial enviada no header `Authorization`. Obrigatória para Basic (Base64 de `usuario:senha`) e Jwt (token).
     *
     * @var string|null
     */
    public $credential;

    public function __construct(
        int $type,
        string $url,
        int $authenticationType,
        #[\SensitiveParameter]
        ?string $credential = null
    ) {
        $this->type = $type;
        $this->url = $url;
        $this->authenticationType = $authenticationType;
        $this->credential = $credential;
    }

    /**
     * @return array<string, string>
     */
    protected function sensitiveFields(): array
    {
        return [
            'credential' => 'full',
        ];
    }
}
