<?php

declare(strict_types=1);

namespace Twila\Credicarro\Tests;

use PHPUnit\Framework\TestCase;
use Twila\Credicarro\Proposals\CreateProposalRequest;
use Twila\Credicarro\Proposals\ProposalStatus;
use Twila\Credicarro\Proposals\ProposalType;
use Twila\Credicarro\Errors\CredicarroApiException;
use Twila\Credicarro\Errors\CredicarroBusinessRuleException;
use Twila\Credicarro\Simulations\CreateFinancingSimulationRequest;
use Twila\Credicarro\Simulations\CreateFinancingWithoutCpfRequest;
use Twila\Credicarro\Simulations\CreateRefinancingSimulationRequest;
use Twila\Credicarro\Simulations\CreateRefinancingWithoutCpfRequest;

final class V1ClientTest extends TestCase
{
    private const ID = TestClientFactory::UUID;

    public function testFinancingRangeUsesV1RouteWithoutStoreCnpj(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, [
            'opcoes_por_prazo' => [['prazo' => 12, 'valor_minimo_entrada' => 5000]],
        ])]);

        $options = $h->client->v1->simulations->getFinancingRange('ABC1D23');

        self::assertCount(1, $options);
        self::assertSame(5000.0, $options[0]->minimumDownPayment);
        $uri = $h->lastApiRequest()->getUri();
        self::assertSame('/integration/v1/proposal/simulation/range/financing', $uri->getPath());
        self::assertSame('placa=ABC1D23', $uri->getQuery());
    }

    public function testRefinancingRangeUsesV1Route(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, [
            'opcoes_por_prazo' => [
                ['prazo' => 6, 'valor_minimo_refinanciavel' => 1000, 'valor_maximo_refinanciavel' => 2000],
            ],
        ])]);

        $options = $h->client->v1->simulations->getRefinancingRange('ABC1D23');

        self::assertSame(2000.0, $options[0]->maximumAmount);
        self::assertSame(
            '/integration/v1/proposal/simulation/range/refinancing',
            $h->lastApiRequest()->getUri()->getPath()
        );
    }

    public function testCreateFinancingNeverSendsStoreCnpj(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(201, ['simulacoes' => []])]);

        $h->client->v1->simulations->createFinancing(
            new CreateFinancingSimulationRequest(
                '12345678909',
                'ABC1D23',
                45000.0,
                5000.0,
                'SP',
                true,
                '11222333000181'
            )
        );

        $request = $h->lastApiRequest();
        self::assertSame('/integration/v1/proposal/simulation/financing', $request->getUri()->getPath());
        self::assertEquals([
            'cpf' => '12345678909',
            'placa' => 'ABC1D23',
            'valorVeiculo' => 45000.0,
            'valorEntrada' => 5000.0,
            'uf' => 'SP',
            'isVeiculoZeroKm' => true,
        ], TestClientFactory::bodyOf($request));
    }

    public function testCreateRefinancingNeverSendsStoreCnpj(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(201, ['simulacoes' => []])]);

        $h->client->v1->simulations->createRefinancing(
            new CreateRefinancingSimulationRequest('12345678909', 'ABC1D23', 25000.0)
        );

        $request = $h->lastApiRequest();
        self::assertSame('/integration/v1/proposal/simulation/refinancing', $request->getUri()->getPath());
        self::assertEquals(
            ['cpf' => '12345678909', 'placa' => 'ABC1D23', 'valorSolicitado' => 25000.0],
            TestClientFactory::bodyOf($request)
        );
    }

    public function testCreateFinancingWithoutCpfMapsOptionsAndSendsTheDownPayment(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, ['cotacoes' => [
            self::withoutCpfOption(12, 1200.5, 5000.0, 10.0),
            self::withoutCpfOption(24, 700.25, 5000.0),
        ]])]);

        $options = $h->client->v1->simulations->createFinancingWithoutCpf(
            new CreateFinancingWithoutCpfRequest('ABC1D23', 45000.0, 'SP', 5000.0)
        );

        $request = $h->lastApiRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/integration/v1/proposal/simulation/financing/without-cpf', $request->getUri()->getPath());
        self::assertEquals(
            ['placa' => 'ABC1D23', 'valorVeiculo' => 45000.0, 'uf' => 'SP', 'valorEntrada' => 5000.0],
            TestClientFactory::bodyOf($request)
        );
        self::assertCount(2, $options);
        self::assertSame(12, $options[0]->term);
        self::assertSame(1200.5, $options[0]->installmentAmount);
        self::assertSame(39000.0, $options[0]->requestedAmount);
        self::assertSame(1500.0, $options[0]->tacAmount);
        self::assertSame(2.1, $options[0]->monthlyInterestRate);
        self::assertSame(28.3, $options[0]->annualInterestRate);
        self::assertSame(2.5, $options[0]->monthlyCet);
        self::assertSame(34.4, $options[0]->annualCet);
        self::assertSame(5000.0, $options[0]->minimumDownPayment);
        self::assertSame(10.0, $options[0]->approximateDailyRetention);
        self::assertNull($options[1]->approximateDailyRetention);
        self::assertSame(24, $options[1]->term);
    }

    public function testCreateFinancingWithoutCpfOmitsTheDownPaymentWhenNotInformed(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, ['cotacoes' => []])]);

        $options = $h->client->v1->simulations->createFinancingWithoutCpf(
            new CreateFinancingWithoutCpfRequest('ABC1D23', 45000.0, 'SP')
        );

        self::assertSame([], $options);
        $body = TestClientFactory::bodyOf($h->lastApiRequest());
        self::assertArrayNotHasKey('valorEntrada', $body);
        self::assertEquals(['placa' => 'ABC1D23', 'valorVeiculo' => 45000.0, 'uf' => 'SP'], $body);
    }

    public function testCreateFinancingWithoutCpfNeverSendsIdempotencyKey(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, ['cotacoes' => []])]);

        $h->client->v1->simulations->createFinancingWithoutCpf(
            new CreateFinancingWithoutCpfRequest('ABC1D23', 45000.0, 'SP')
        );

        self::assertFalse($h->lastApiRequest()->hasHeader('Idempotency-Key'));
    }

    public function testCreateFinancingWithoutCpfIsRetriedOnTransientFailure(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(503, ['tipo' => 'Servico.Indisponivel']),
            TestClientFactory::json(200, ['cotacoes' => []]),
        ]);

        $h->client->v1->simulations->createFinancingWithoutCpf(
            new CreateFinancingWithoutCpfRequest('ABC1D23', 45000.0, 'SP')
        );

        $requests = $h->apiRequests();
        self::assertCount(2, $requests);
        self::assertFalse($requests[0]->hasHeader('Idempotency-Key'));
        self::assertFalse($requests[1]->hasHeader('Idempotency-Key'));
    }

    public function testCreateFinancingWithoutCpfMapsNotFoundError(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(404, [
            'tipo' => 'Fipe.VehiclePlateNotFound',
            'titulo' => 'Placa não encontrada',
        ])]);

        try {
            $h->client->v1->simulations->createFinancingWithoutCpf(
                new CreateFinancingWithoutCpfRequest('ABC1D23', 45000.0, 'SP')
            );
            self::fail('Esperava CredicarroApiException.');
        } catch (CredicarroApiException $e) {
            self::assertSame(404, $e->getStatusCode());
            self::assertSame('Fipe.VehiclePlateNotFound', $e->getErrorCode());
        }
    }

    public function testCreateFinancingWithoutCpfMapsBusinessRuleError(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(422, [
            'tipo' => 'Proposal.DownPaymentInvalid',
            'titulo' => 'Regra de negócio não atendida',
        ])]);

        try {
            $h->client->v1->simulations->createFinancingWithoutCpf(
                new CreateFinancingWithoutCpfRequest('ABC1D23', 45000.0, 'SP', 99999.0)
            );
            self::fail('Esperava CredicarroBusinessRuleException.');
        } catch (CredicarroBusinessRuleException $e) {
            self::assertSame('Proposal.DownPaymentInvalid', $e->getErrorCode());
        }
    }

    public function testCreateRefinancingWithoutCpfMapsOptionsAndSendsRequiredFields(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, ['cotacoes' => [
            self::refinancingWithoutCpfOption(12, 900.5, 10.0),
            self::refinancingWithoutCpfOption(24, 500.25),
        ]])]);

        $options = $h->client->v1->simulations->createRefinancingWithoutCpf(
            new CreateRefinancingWithoutCpfRequest('ABC1D23', 25000.0, 'SP')
        );

        $request = $h->lastApiRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/integration/v1/proposal/simulation/refinancing/without-cpf', $request->getUri()->getPath());
        self::assertEquals(
            ['placa' => 'ABC1D23', 'valorSolicitado' => 25000.0, 'uf' => 'SP'],
            TestClientFactory::bodyOf($request)
        );
        self::assertCount(2, $options);
        self::assertSame(12, $options[0]->term);
        self::assertSame(900.5, $options[0]->installmentAmount);
        self::assertSame(25000.0, $options[0]->requestedAmount);
        self::assertSame(2.1, $options[0]->monthlyInterestRate);
        self::assertSame(28.3, $options[0]->annualInterestRate);
        self::assertSame(2.5, $options[0]->monthlyCet);
        self::assertSame(34.4, $options[0]->annualCet);
        self::assertSame(10000.0, $options[0]->minimumAmount);
        self::assertSame(30000.0, $options[0]->maximumAmount);
        self::assertSame(10.0, $options[0]->approximateDailyRetention);
        self::assertNull($options[1]->approximateDailyRetention);
        self::assertSame(24, $options[1]->term);
    }

    public function testCreateRefinancingWithoutCpfNeverSendsIdempotencyKey(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, ['cotacoes' => []])]);

        $options = $h->client->v1->simulations->createRefinancingWithoutCpf(
            new CreateRefinancingWithoutCpfRequest('ABC1D23', 25000.0, 'SP')
        );

        self::assertSame([], $options);
        self::assertFalse($h->lastApiRequest()->hasHeader('Idempotency-Key'));
    }

    public function testCreateRefinancingWithoutCpfIsRetriedOnTransientFailure(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(503, ['tipo' => 'Servico.Indisponivel']),
            TestClientFactory::json(200, ['cotacoes' => []]),
        ]);

        $h->client->v1->simulations->createRefinancingWithoutCpf(
            new CreateRefinancingWithoutCpfRequest('ABC1D23', 25000.0, 'SP')
        );

        $requests = $h->apiRequests();
        self::assertCount(2, $requests);
        self::assertFalse($requests[0]->hasHeader('Idempotency-Key'));
        self::assertFalse($requests[1]->hasHeader('Idempotency-Key'));
    }

    public function testCreateRefinancingWithoutCpfMapsNotFoundError(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(404, [
            'tipo' => 'Fipe.VehiclePlateNotFound',
            'titulo' => 'Placa não encontrada',
        ])]);

        try {
            $h->client->v1->simulations->createRefinancingWithoutCpf(
                new CreateRefinancingWithoutCpfRequest('ABC1D23', 25000.0, 'SP')
            );
            self::fail('Esperava CredicarroApiException.');
        } catch (CredicarroApiException $e) {
            self::assertSame(404, $e->getStatusCode());
            self::assertSame('Fipe.VehiclePlateNotFound', $e->getErrorCode());
        }
    }

    public function testCreateRefinancingWithoutCpfMapsBusinessRuleError(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(422, [
            'tipo' => 'Proposal.RequestAmountInvalid',
            'titulo' => 'Regra de negócio não atendida',
        ])]);

        try {
            $h->client->v1->simulations->createRefinancingWithoutCpf(
                new CreateRefinancingWithoutCpfRequest('ABC1D23', 999999.0, 'SP')
            );
            self::fail('Esperava CredicarroBusinessRuleException.');
        } catch (CredicarroBusinessRuleException $e) {
            self::assertSame('Proposal.RequestAmountInvalid', $e->getErrorCode());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function refinancingWithoutCpfOption(int $term, float $installment, ?float $retention = null): array
    {
        $wire = [
            'valor_solicitado' => 25000,
            'prazo' => $term,
            'valor_parcela' => $installment,
            'valor_tac' => 1500,
            'valor_boleto' => 4.5,
            'valor_seguro' => 0,
            'valor_iof' => 900,
            'percentual_iof' => 2.3,
            'valor_credito_total' => 26500,
            'valor_divida_total' => 35000,
            'percentual_cet_mensal' => 2.5,
            'percentual_cet_anual' => 34.4,
            'percentual_juros_mensal' => 2.1,
            'percentual_juros_anual' => 28.3,
            'valor_minimo_refinanciavel' => 10000,
            'valor_maximo_refinanciavel' => 30000,
        ];

        if ($retention !== null) {
            $wire['valor_retencao_diaria_aproximado'] = $retention;
        }

        return $wire;
    }

    /**
     * @return array<string, mixed>
     */
    private static function withoutCpfOption(
        int $term,
        float $installment,
        float $minimumDownPayment,
        ?float $retention = null
    ): array {
        $wire = [
            'valor_solicitado' => 39000,
            'prazo' => $term,
            'valor_parcela' => $installment,
            'valor_tac' => 1500,
            'valor_boleto' => 4.5,
            'valor_seguro' => 0,
            'valor_iof' => 900,
            'percentual_iof' => 2.3,
            'valor_credito_total' => 40500,
            'valor_divida_total' => 55000,
            'percentual_cet_mensal' => 2.5,
            'percentual_cet_anual' => 34.4,
            'percentual_juros_mensal' => 2.1,
            'percentual_juros_anual' => 28.3,
            'valor_entrada_minima' => $minimumDownPayment,
        ];

        if ($retention !== null) {
            $wire['valor_retencao_diaria_aproximado'] = $retention;
        }

        return $wire;
    }

    public function testProposalCreateAndGetUseV1Routes(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(201, ['id_proposta' => self::ID]),
            TestClientFactory::json(200, [
                'id_proposta' => self::ID,
                'numero' => 7,
                'id_simulacao' => self::ID,
                'tipo' => 'Financing',
                'status' => 'Refused',
                'comprador' => ['cpf' => '123.456.789-01'],
                'data_criacao' => '2025-10-17T19:05:08.984Z',
            ]),
        ]);

        $id = $h->client->v1->proposals->create(new CreateProposalRequest('12345678909', self::ID));
        $summary = $h->client->v1->proposals->get($id);

        $requests = $h->apiRequests();
        self::assertSame('/integration/v1/proposal', $requests[0]->getUri()->getPath());
        self::assertSame('/integration/v1/proposal/' . self::ID, $requests[1]->getUri()->getPath());
        self::assertSame(ProposalType::FINANCING, $summary->type);
        self::assertSame(ProposalStatus::REFUSED, $summary->status);
        self::assertSame('Refused', $summary->statusName);
        self::assertSame('123.456.789-01', $summary->buyerCpf);
    }
}
