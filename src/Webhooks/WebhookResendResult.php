<?php

declare(strict_types=1);

namespace Twila\Credicarro\Webhooks;

use Twila\Credicarro\Internal\Masking\MasksSensitiveData;

/**
 * Resultado imediato do reenvio.
 */
final class WebhookResendResult
{
    use MasksSensitiveData;

    /** @var int|null status HTTP devolvido pela sua URL; `null` quando ela não respondeu (`status_http`) */
    public $statusCode;

    /** @var string corpo devolvido pela sua URL (`resposta`) */
    public $response;

    public function __construct(?int $statusCode, string $response)
    {
        $this->statusCode = $statusCode;
        $this->response = $response;
    }

    /**
     * @return array<string, string>
     */
    protected function sensitiveFields(): array
    {
        return [
            'response' => 'full',
        ];
    }
}
