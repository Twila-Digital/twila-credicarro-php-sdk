<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Mapping;

use Twila\Credicarro\Internal\Http\QueryStringBuilder;
use Twila\Credicarro\Webhooks\CreateWebhookRequest;
use Twila\Credicarro\Webhooks\CreateWebhookResult;
use Twila\Credicarro\Webhooks\UpdateWebhookRequest;
use Twila\Credicarro\Webhooks\Webhook;
use Twila\Credicarro\Webhooks\WebhookAuthenticationType;
use Twila\Credicarro\Webhooks\WebhookDelivery;
use Twila\Credicarro\Webhooks\WebhookDeliveryFilter;
use Twila\Credicarro\Webhooks\WebhookResendResult;
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

    public static function deliveryFilterToQuery(
        QueryStringBuilder $query,
        ?WebhookDeliveryFilter $filter
    ): QueryStringBuilder {
        if ($filter === null) {
            return $query;
        }

        return $query
            ->add('data_inicial', $filter->initialDate)
            ->add('data_final', $filter->finalDate)
            ->add('id_proposta', $filter->proposalId)
            ->add('id_simulacao', $filter->simulationId)
            ->add('numero_proposta', $filter->proposalNumber)
            ->add('status_http', $filter->statusCode);
    }

    /**
     * @param array<mixed> $wire
     */
    public static function deliveryFromWire(array $wire): WebhookDelivery
    {
        $type = Wire::nullableString($wire, 'tipo');

        return new WebhookDelivery(
            Wire::string($wire, 'id_auditoria'),
            Wire::nullableString($wire, 'id_webhook'),
            WebhookType::fromWireName($type),
            $type,
            Wire::string($wire, 'requisicao'),
            Wire::string($wire, 'resposta'),
            Wire::nullableInt($wire, 'status_http'),
            Wire::string($wire, 'data_criacao')
        );
    }

    /**
     * @param array<mixed> $wire
     */
    public static function resendResultFromWire(array $wire): WebhookResendResult
    {
        return new WebhookResendResult(Wire::nullableInt($wire, 'status_http'), Wire::string($wire, 'resposta'));
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
