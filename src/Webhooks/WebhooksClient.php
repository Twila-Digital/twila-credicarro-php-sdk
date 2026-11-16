<?php

declare(strict_types=1);

namespace Twila\Credicarro\Webhooks;

use Twila\Credicarro\Internal\Http\ApiRequestExecutor;
use Twila\Credicarro\Internal\Http\QueryStringBuilder;
use Twila\Credicarro\Internal\Http\ResponseReader;
use Twila\Credicarro\Internal\Mapping\PagedMapper;
use Twila\Credicarro\Internal\Mapping\WebhookMapper;
use Twila\Credicarro\Internal\Validation;
use Twila\Credicarro\ListRequest;
use Twila\Credicarro\PagedResult;

/**
 * Gerenciamento dos webhooks do parceiro. Para validar as notificações recebidas, use `CredicarroWebhookEvent`.
 */
final class WebhooksClient
{
    /** O reenvio espera a resposta da URL do integrador (até 15 s do lado da Credicarro). */
    private const RESEND_ATTEMPT_TIMEOUT_MS = 30000;

    /** @var ApiRequestExecutor */
    private $executor;

    public function __construct(ApiRequestExecutor $executor)
    {
        $this->executor = $executor;
    }

    /**
     * Cadastra um webhook ativo (`POST v1/webhook`). É permitido mais de um por tipo.
     *
     * O `signingSecret` só vem nesta resposta. A rota não aceita Idempotency-Key, então o SDK NÃO repete a chamada
     * em caso de falha de rede ou 5xx: consulte `list()` antes de tentar de novo, para não duplicar o cadastro.
     */
    public function create(CreateWebhookRequest $request): CreateWebhookResult
    {
        $response = $this->executor->post('v1/webhook', WebhookMapper::createRequestToWire($request));
        ApiRequestExecutor::ensureSuccess($response);

        return WebhookMapper::createResultFromWire(ResponseReader::object($response));
    }

    /**
     * Lista os webhooks de forma paginada (`GET v1/webhook`). Os itens são `Webhook`.
     */
    public function list(?ListRequest $request = null): PagedResult
    {
        $response = $this->executor->get(PagedMapper::path('v1/webhook', $request));
        ApiRequestExecutor::ensureSuccess($response);

        return PagedMapper::fromWire($response->body, [WebhookMapper::class, 'webhookFromWire']);
    }

    /**
     * Atualiza tipo, URL e status (`PUT v1/webhook/{id}`, 204). Segura para repetição.
     */
    public function update(string $webhookId, UpdateWebhookRequest $request): void
    {
        $id = Validation::requireUuid($webhookId, 'webhookId');

        $response = $this->executor->put(
            'v1/webhook/' . rawurlencode($id),
            WebhookMapper::updateRequestToWire($request)
        );
        ApiRequestExecutor::ensureSuccess($response);
    }

    /**
     * Exclui o webhook (`DELETE v1/webhook/{id}`, 204). Segura para repetição.
     */
    public function delete(string $webhookId): void
    {
        $id = Validation::requireUuid($webhookId, 'webhookId');

        $response = $this->executor->delete('v1/webhook/' . rawurlencode($id));
        ApiRequestExecutor::ensureSuccess($response);
    }

    /**
     * Histórico de envios, com o corpo enviado, a resposta da sua URL e o status HTTP (`GET v1/webhook/audit`).
     * Todos os filtros são opcionais. Os itens são `WebhookDelivery`.
     */
    public function listDeliveries(?WebhookDeliveryFilter $filter = null, ?ListRequest $paging = null): PagedResult
    {
        $pagedPath = PagedMapper::path('v1/webhook/audit', $paging);
        $filterQuery = WebhookMapper::deliveryFilterToQuery(new QueryStringBuilder(), $filter)->build('');
        $path = $filterQuery === '' ? $pagedPath : $pagedPath . '&' . substr($filterQuery, 1);

        $response = $this->executor->get($path);
        ApiRequestExecutor::ensureSuccess($response);

        return PagedMapper::fromWire($response->body, [WebhookMapper::class, 'deliveryFromWire']);
    }

    /**
     * Sucessos e erros por intervalo, no horário de Brasília (`GET v1/webhook/audit/chart`). `initialDate` e
     * `finalDate` são obrigatórios, e o período pode ter até 31 dias.
     *
     * @return array<int, WebhookDeliveryChartPoint>
     */
    public function getDeliveryChart(WebhookDeliveryFilter $filter): array
    {
        Validation::requireNotBlank($filter->initialDate, 'data_inicial');
        Validation::requireNotBlank($filter->finalDate, 'data_final');

        $path = WebhookMapper::deliveryFilterToQuery(new QueryStringBuilder(), $filter)->build('v1/webhook/audit/chart');
        $response = $this->executor->get($path);
        ApiRequestExecutor::ensureSuccess($response);

        return WebhookMapper::deliveryChartFromWire($response->body);
    }

    /**
     * Reenvia para a sua URL o mesmo corpo de um envio do histórico (`POST v1/webhook/audit/{id}/resend`).
     *
     * Uma única tentativa: o SDK NÃO repete a chamada (repetir dispararia o webhook de novo). O reenvio chega com uma
     * `Idempotency-Key` nova (`{id_mensagem}:{id_webhook}:{id_tentativa}`).
     */
    public function resend(string $deliveryId): WebhookResendResult
    {
        $id = Validation::requireUuid($deliveryId, 'auditWebhookId');

        $response = $this->executor->post(
            'v1/webhook/audit/' . rawurlencode($id) . '/resend',
            null,
            null,
            self::RESEND_ATTEMPT_TIMEOUT_MS
        );
        ApiRequestExecutor::ensureSuccess($response);

        return WebhookMapper::resendResultFromWire(ResponseReader::object($response));
    }
}
