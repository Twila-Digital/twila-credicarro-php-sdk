<?php

declare(strict_types=1);

namespace Twila\Credicarro\Webhooks;

use Twila\Credicarro\Internal\Http\ApiRequestExecutor;
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
}
