<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Mapping;

use Twila\Credicarro\Webhooks\CreateWebhookRequest;
use Twila\Credicarro\Webhooks\CreateWebhookResult;
use Twila\Credicarro\Webhooks\UpdateWebhookRequest;
use Twila\Credicarro\Webhooks\Webhook;
use Twila\Credicarro\Webhooks\WebhookAuthenticationType;
use Twila\Credicarro\Webhooks\WebhookStatus;
use Twila\Credicarro\Webhooks\WebhookType;

/**
 * @internal
 */
final class WebhookMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function createRequestToWire(CreateWebhookRequest $request): array
    {
        return Wire::withoutNulls([
            'tipo' => $request->type,
            'url' => $request->url,
            'tipo_autenticacao' => $request->authenticationType,
            'credencial' => $request->credential,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function updateRequestToWire(UpdateWebhookRequest $request): array
    {
        return Wire::withoutNulls([
            'tipo' => $request->type,
            'url' => $request->url,
            'status' => $request->status,
        ]);
    }

    /**
     * @param array<mixed> $wire
     */
    public static function createResultFromWire(array $wire): CreateWebhookResult
    {
        return new CreateWebhookResult(
            Wire::string($wire, 'id_webhook'),
            Wire::string($wire, 'segredo_assinatura')
        );
    }

    /**
     * @param array<mixed> $wire
     */
    public static function webhookFromWire(array $wire): Webhook
    {
        $type = Wire::nullableString($wire, 'tipo');
        $authentication = Wire::nullableString($wire, 'tipo_autenticacao');
        $status = Wire::nullableString($wire, 'status');

        return new Webhook(
            Wire::string($wire, 'id_webhook'),
            WebhookType::fromWireName($type),
            $type,
            Wire::string($wire, 'url'),
            WebhookAuthenticationType::fromWireName($authentication),
            $authentication,
            WebhookStatus::fromWireName($status),
            $status
        );
    }
}
