<?php

declare(strict_types=1);

namespace Twila\Credicarro\Tests;

use PHPUnit\Framework\TestCase;
use Twila\Credicarro\Proposals\CreateProposalRequest;
use Twila\Credicarro\Proposals\ProposalStatus;
use Twila\Credicarro\Proposals\ProposalType;
use Twila\Credicarro\Simulations\CreateFinancingSimulationRequest;
use Twila\Credicarro\Simulations\CreateRefinancingSimulationRequest;

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
