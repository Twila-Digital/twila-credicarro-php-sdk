<?php

declare(strict_types=1);

namespace Twila\Credicarro\Tests;

use PHPUnit\Framework\TestCase;
use Twila\Credicarro\Errors\CredicarroApiException;
use Twila\Credicarro\Errors\CredicarroBusinessRuleException;
use Twila\Credicarro\Errors\CredicarroValidationException;
use Twila\Credicarro\ListRequest;
use Twila\Credicarro\Simulations\CommissionReturnTarget;
use Twila\Credicarro\Simulations\CreateFinancingSimulationRequest;
use Twila\Credicarro\Simulations\CreateRefinancingSimulationRequest;
use Twila\Credicarro\Simulations\SimulationType;
use Twila\Credicarro\Webhooks\WebhookDeliveryFilter;
use Twila\Credicarro\Webhooks\WebhookType;

final class WebhookDeliveriesTest extends TestCase
{
    private const AUDIT_ID = '7c9e6679-7425-40de-944b-e07fc1f90ae7';
    private const WEBHOOK_ID = '3fa85f64-5717-4562-b3fc-2c963f66afa6';

    private const OPTION = [
        'id_simulacao' => self::WEBHOOK_ID,
        'valor_solicitado' => 40000,
        'prazo' => 36,
        'valor_parcela' => 2042.42,
        'valor_tac' => 1500,
        'valor_boleto' => 3.5,
        'valor_seguro' => 0,
        'valor_iof' => 872,
        'percentual_iof' => 1.92,
        'valor_credito_total' => 42722,
        'valor_divida_total' => 73527.12,
        'percentual_cet_mensal' => 3.62,
        'percentual_cet_anual' => 53.25,
        'percentual_juros_mensal' => 3.29,
        'percentual_juros_anual' => 47.53,
    ];

    public function testListDeliveriesSendsFiltersAndPagingAndMapsItems(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, [
            'itens' => [
                [
                    'id_auditoria' => self::AUDIT_ID,
                    'id_webhook' => self::WEBHOOK_ID,
                    'tipo' => 'Proposal',
                    'requisicao' => '{"id_proposta":"x"}',
                    'resposta' => '{"recebido":true}',
                    'status_http' => 200,
                    'data_criacao' => '2026-11-05T14:32:10Z',
                ],
                [
                    'id_auditoria' => self::AUDIT_ID,
                    'id_webhook' => null,
                    'tipo' => 'Algo',
                    'requisicao' => '{}',
                    'resposta' => '',
                    'status_http' => null,
                    'data_criacao' => '2026-11-05T14:33:00Z',
                ],
            ],
            'pagina' => ['tem_proximo' => true, 'tem_anterior' => false, 'numero' => 2, 'tamanho' => 50, 'total' => 51],
        ])]);

        $page = $h->client->webhooks->listDeliveries(
            new WebhookDeliveryFilter('2026-11-01', '2026-11-05T23:59:59-03:00', null, null, '12345', 500),
            new ListRequest(2, 50)
        );

        $request = $h->lastApiRequest();
        self::assertSame('/integration/v1/webhook/audit', $request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(), $query);
        self::assertEquals([
            'pagina_inicial' => '2',
            'limite' => '50',
            'data_inicial' => '2026-11-01',
            'data_final' => '2026-11-05T23:59:59-03:00',
            'numero_proposta' => '12345',
            'status_http' => '500',
        ], $query);

        [$first, $second] = $page->items;
        self::assertSame(self::AUDIT_ID, $first->id);
        self::assertSame(self::WEBHOOK_ID, $first->webhookId);
        self::assertSame(WebhookType::PROPOSAL, $first->type);
        self::assertSame(200, $first->statusCode);
        self::assertSame('{"recebido":true}', $first->response);
        self::assertNull($second->webhookId);
        self::assertSame(WebhookType::UNKNOWN, $second->type);
        self::assertNull($second->statusCode);
        self::assertStringNotContainsString('id_proposta', print_r($first, true));
    }

    public function testListDeliveriesWithoutFiltersSendsOnlyPaging(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, ['itens' => [], 'pagina' => []])]);

        $h->client->webhooks->listDeliveries();

        self::assertSame('pagina_inicial=1&limite=10', $h->lastApiRequest()->getUri()->getQuery());
    }

    public function testGetDeliveryChartSendsPeriodAndMapsPoints(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, [
            ['rotulo' => '04', 'sucessos' => 42, 'erros' => 0],
            ['rotulo' => '05', 'sucessos' => 38, 'erros' => 3],
        ])]);

        $points = $h->client->webhooks->getDeliveryChart(new WebhookDeliveryFilter('2026-11-04', '2026-11-05'));

        $request = $h->lastApiRequest();
        self::assertSame('/integration/v1/webhook/audit/chart', $request->getUri()->getPath());
        self::assertSame('data_inicial=2026-11-04&data_final=2026-11-05', $request->getUri()->getQuery());
        self::assertCount(2, $points);
        self::assertSame('05', $points[1]->label);
        self::assertSame(38, $points[1]->successCount);
        self::assertSame(3, $points[1]->errorCount);
    }

    public function testGetDeliveryChartRequiresThePeriod(): void
    {
        $h = TestClientFactory::harness([]);

        $this->expectException(CredicarroValidationException::class);
        $h->client->webhooks->getDeliveryChart(new WebhookDeliveryFilter('2026-11-04'));
    }

    public function testResendPostsWithoutBodyOrIdempotencyKey(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, ['status_http' => null, 'resposta' => ''])]);

        $result = $h->client->webhooks->resend(self::AUDIT_ID);

        $request = $h->lastApiRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/integration/v1/webhook/audit/' . self::AUDIT_ID . '/resend', $request->getUri()->getPath());
        self::assertSame('', (string) $request->getBody());
        self::assertFalse($request->hasHeader('Idempotency-Key'));
        self::assertNull($result->statusCode);
        self::assertSame('', $result->response);
    }

    public function testResendIsNotRetriedOn503(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(503, [
                'tipo' => 'Organization.Webhook.Resend.DependencyUnavailable',
                'titulo' => 'Indisponível',
                'status' => 503,
            ]),
            TestClientFactory::json(200, ['status_http' => 200, 'resposta' => 'ok']),
        ]);

        try {
            $h->client->webhooks->resend(self::AUDIT_ID);
            self::fail('Esperava CredicarroApiException.');
        } catch (CredicarroApiException $e) {
            self::assertSame('Organization.Webhook.Resend.DependencyUnavailable', $e->getErrorCode());
        }

        self::assertCount(1, $h->apiRequests());
    }

    public function testResendRequiresUuid(): void
    {
        $h = TestClientFactory::harness([]);

        $this->expectException(CredicarroValidationException::class);
        $h->client->webhooks->resend('nao-e-uuid');
    }

    public function testGetCommissionReturnsMapsRanges(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, [
            'retornos_comissao' => [
                ['tipo' => 'Financing', 'alvo' => 'Store', 'retorno_minimo' => 0, 'retorno_maximo' => 4, 'permite_taxa_padrao' => true],
                ['tipo' => 'Outro', 'alvo' => 'Novo', 'retorno_minimo' => 1, 'retorno_maximo' => 3, 'permite_taxa_padrao' => false],
            ],
        ])]);

        $returns = $h->client->simulations->getCommissionReturns('11.222.333/0001-81');

        $request = $h->lastApiRequest();
        self::assertSame('/integration/v2/proposal/simulation/commission-returns', $request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(), $query);
        self::assertSame(['cnpjLoja' => '11.222.333/0001-81'], $query);
        self::assertSame(SimulationType::FINANCING, $returns[0]->type);
        self::assertSame(CommissionReturnTarget::STORE, $returns[0]->target);
        self::assertSame(4, $returns[0]->maximumReturn);
        self::assertTrue($returns[0]->allowsDefaultRate);
        self::assertSame(SimulationType::UNKNOWN, $returns[1]->type);
        self::assertSame(CommissionReturnTarget::UNKNOWN, $returns[1]->target);
        self::assertSame('Novo', $returns[1]->targetRaw);
    }

    public function testSimulationsSendCommissionReturnOnlyWhenProvidedAndOnlyOnV2(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(201, ['simulacoes' => [self::OPTION]]),
            TestClientFactory::json(201, ['simulacoes' => [self::OPTION]]),
            TestClientFactory::json(201, ['simulacoes' => [self::OPTION]]),
        ]);

        $h->client->simulations->createFinancing(
            new CreateFinancingSimulationRequest('12345678909', 'ABC1D23', 45000.0, 5000.0, 'SP', false, '11222333000181', 2)
        );
        $h->client->simulations->createRefinancing(
            new CreateRefinancingSimulationRequest('12345678909', 'ABC1D23', 25000.0, '11222333000181')
        );
        $h->client->v1->simulations->createFinancing(
            new CreateFinancingSimulationRequest('12345678909', 'ABC1D23', 45000.0, 5000.0, 'SP', false, null, 2)
        );

        $requests = $h->apiRequests();
        self::assertSame(2, TestClientFactory::bodyOf($requests[0])['retornoComissao']);
        self::assertArrayNotHasKey('retornoComissao', TestClientFactory::bodyOf($requests[1]));
        self::assertArrayNotHasKey('retornoComissao', TestClientFactory::bodyOf($requests[2]));
    }

    public function testCommissionReturnOutOfRangeIsABusinessRule(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(422, [
            'tipo' => 'Store.CommissionReturn.OutOfRange',
            'titulo' => 'Regra de negócio não atendida',
            'status' => 422,
        ])]);

        try {
            $h->client->simulations->createFinancing(
                new CreateFinancingSimulationRequest('12345678909', 'ABC1D23', 45000.0, 5000.0, 'SP', false, '11222333000181', 9)
            );
            self::fail('Esperava CredicarroBusinessRuleException.');
        } catch (CredicarroBusinessRuleException $e) {
            self::assertSame('Store.CommissionReturn.OutOfRange', $e->getErrorCode());
        }
    }
}
