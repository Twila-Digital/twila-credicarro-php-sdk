<?php

declare(strict_types=1);

namespace Twila\Credicarro\Tests;

use PHPUnit\Framework\TestCase;
use Twila\Credicarro\Errors\CredicarroBusinessRuleException;
use Twila\Credicarro\Errors\CredicarroValidationException;
use Twila\Credicarro\ListRequest;
use Twila\Credicarro\Simulations\CreateFinancingSimulationRequest;
use Twila\Credicarro\Simulations\CreateRefinancingSimulationRequest;
use Twila\Credicarro\Simulations\SimulationType;

final class SimulationsClientTest extends TestCase
{
    private const OPTION_WIRE = [
        'id_simulacao' => '123e4567-e89b-12d3-a456-426614174000',
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
        'valor_retencao_diaria_aproximado' => 12.5,
    ];

    private const SIMULATION_WIRE = [
        'id_simulacao' => 'bea8d060-f93d-48eb-b6c4-45f64483cdd9',
        'prazo' => 24,
        'valor_parcela' => 2356,
        'valor_solicitado' => 37908,
        'valor_entrada' => 9786,
        'valor_bem' => 47694,
        'valor_tac' => 1500,
        'taxa_juros_negociada' => 2.79,
        'valor_registro' => 487,
        'uf' => 'SP',
        'tipo' => 'Refinancing',
        'transacao' => ['valor_total_credito' => 78495, 'valor_total_debito' => 78496],
        'juros' => ['percentual_juros_mensal' => 3.29, 'percentual_juros_anual' => 39.48],
        'cet' => ['percentual_mensal' => 3.62, 'percentual_anual' => 43.44],
        'iof' => ['valor' => 872, 'percentual' => 1.92],
        'veiculo' => [
            'marca' => 'Honda',
            'modelo' => 'Civic',
            'versao' => 'Turing',
            'ano' => 2024,
            'valor' => 124654,
            'placa' => 'MWO-3910',
            'veiculo_zero_km' => false,
            'chassi' => '1Y5Ye4U8kydsr4828',
            'renavam' => '70502561697',
            'cor' => 'Prata',
        ],
        'cliente' => ['cpf' => '123.456.789-01'],
        'data_criacao' => '2025-10-17T19:05:08.984Z',
    ];

    private const PAGE_WIRE = [
        'tem_proximo' => true,
        'tem_anterior' => false,
        'numero' => 1,
        'tamanho' => 10,
        'total' => 25,
    ];

    public function testGetFinancingRangeSendsPlateAndStoreCnpjAsQuery(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, [
            'opcoes_por_prazo' => [
                ['prazo' => 12, 'valor_minimo_entrada' => 5000.0],
                ['prazo' => 24, 'valor_minimo_entrada' => 4000],
            ],
        ])]);

        $options = $h->client->simulations->getFinancingRange('ABC-1234', '11.222.333/0001-81');

        self::assertCount(2, $options);
        self::assertSame(12, $options[0]->term);
        self::assertSame(5000.0, $options[0]->minimumDownPayment);
        self::assertSame(4000.0, $options[1]->minimumDownPayment);
        $uri = $h->lastApiRequest()->getUri();
        self::assertSame('/integration/v2/proposal/simulation/range/financing', $uri->getPath());
        self::assertSame('placa=ABC-1234&cnpjLoja=11.222.333%2F0001-81', $uri->getQuery());
    }

    public function testGetRefinancingRangeMapsMinimumAndMaximum(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, [
            'opcoes_por_prazo' => [
                ['prazo' => 6, 'valor_minimo_refinanciavel' => 12480, 'valor_maximo_refinanciavel' => 35720],
            ],
        ])]);

        $options = $h->client->simulations->getRefinancingRange('ABC1D23', '11222333000181');

        self::assertCount(1, $options);
        self::assertSame(6, $options[0]->term);
        self::assertSame(12480.0, $options[0]->minimumAmount);
        self::assertSame(35720.0, $options[0]->maximumAmount);
        self::assertSame(
            '/integration/v2/proposal/simulation/range/refinancing',
            $h->lastApiRequest()->getUri()->getPath()
        );
    }

    public function testRangeRequiresPlateAndStoreCnpj(): void
    {
        $h = TestClientFactory::harness([]);

        $this->expectException(CredicarroValidationException::class);
        $h->client->simulations->getFinancingRange('ABC1D23', '');
    }

    public function testCreateFinancingSendsV2BodyAndMapsOptions(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(201, ['simulacoes' => [self::OPTION_WIRE]])]);

        $options = $h->client->simulations->createFinancing(new CreateFinancingSimulationRequest(
            '12345678909',
            'ABC1D23',
            45000.0,
            5000.0,
            'SP',
            false,
            '11.222.333/0001-81'
        ));

        $request = $h->lastApiRequest();
        self::assertSame('/integration/v2/proposal/simulation/financing', $request->getUri()->getPath());
        self::assertEquals([
            'cpf' => '12345678909',
            'placa' => 'ABC1D23',
            'valorVeiculo' => 45000.0,
            'valorEntrada' => 5000.0,
            'uf' => 'SP',
            'isVeiculoZeroKm' => false,
            'cnpjLoja' => '11.222.333/0001-81',
        ], TestClientFactory::bodyOf($request));

        self::assertCount(1, $options);
        $option = $options[0];
        self::assertSame('123e4567-e89b-12d3-a456-426614174000', $option->simulationId);
        self::assertSame(40000.0, $option->requestedAmount);
        self::assertSame(36, $option->term);
        self::assertSame(2042.42, $option->installmentAmount);
        self::assertSame(1500.0, $option->tacAmount);
        self::assertSame(3.5, $option->boletoAmount);
        self::assertSame(0.0, $option->insuranceAmount);
        self::assertSame(872.0, $option->iofAmount);
        self::assertSame(1.92, $option->iofRate);
        self::assertSame(42722.0, $option->totalCreditAmount);
        self::assertSame(73527.12, $option->totalDebtAmount);
        self::assertSame(3.62, $option->monthlyCet);
        self::assertSame(53.25, $option->annualCet);
        self::assertSame(3.29, $option->monthlyInterestRate);
        self::assertSame(47.53, $option->annualInterestRate);
        self::assertSame(12.5, $option->approximateDailyRetention);
    }

    public function testApproximateDailyRetentionIsNullWhenOmitted(): void
    {
        $wire = self::OPTION_WIRE;
        unset($wire['valor_retencao_diaria_aproximado']);
        $h = TestClientFactory::harness([TestClientFactory::json(201, ['simulacoes' => [$wire]])]);

        $options = $h->client->simulations->createRefinancing(
            new CreateRefinancingSimulationRequest('12345678909', 'ABC1D23', 25000.0, '11222333000181')
        );

        self::assertNull($options[0]->approximateDailyRetention);
        self::assertEquals([
            'cpf' => '12345678909',
            'placa' => 'ABC1D23',
            'valorSolicitado' => 25000.0,
            'cnpjLoja' => '11222333000181',
        ], TestClientFactory::bodyOf($h->lastApiRequest()));
        self::assertSame(
            '/integration/v2/proposal/simulation/refinancing',
            $h->lastApiRequest()->getUri()->getPath()
        );
    }

    public function testV2CreationRequiresStoreCnpj(): void
    {
        $h = TestClientFactory::harness([]);

        try {
            $h->client->simulations->createFinancing(
                new CreateFinancingSimulationRequest('12345678909', 'ABC1D23', 45000.0, 0.0, 'SP', true)
            );
            self::fail('Esperava CredicarroValidationException.');
        } catch (CredicarroValidationException $e) {
            self::assertSame('cnpjLoja', $e->getField());
            self::assertCount(0, $h->tokenRequests());
        }
    }

    public function testBusinessRuleErrorIsSurfaced(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(422, [
            'tipo' => 'Store.Integration.Intermediation',
            'titulo' => 'Regra de negócio não atendida',
        ])]);

        try {
            $h->client->simulations->createRefinancing(
                new CreateRefinancingSimulationRequest('12345678909', 'ABC1D23', 25000.0, '11222333000181')
            );
            self::fail('Esperava CredicarroBusinessRuleException.');
        } catch (CredicarroBusinessRuleException $e) {
            self::assertSame('Store.Integration.Intermediation', $e->getErrorCode());
        }
    }

    public function testGetMapsTheFullSimulation(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, self::SIMULATION_WIRE)]);

        $simulation = $h->client->simulations->get('bea8d060-f93d-48eb-b6c4-45f64483cdd9');

        self::assertSame(
            '/integration/v1/proposal/simulation/bea8d060-f93d-48eb-b6c4-45f64483cdd9',
            $h->lastApiRequest()->getUri()->getPath()
        );
        self::assertSame(24, $simulation->term);
        self::assertSame(2356.0, $simulation->installmentAmount);
        self::assertSame(37908.0, $simulation->requestedAmount);
        self::assertSame(9786.0, $simulation->downPayment);
        self::assertSame(47694.0, $simulation->assetAmount);
        self::assertSame(1500.0, $simulation->tacAmount);
        self::assertSame(2.79, $simulation->negotiatedInterestRate);
        self::assertSame(487.0, $simulation->registrationAmount);
        self::assertSame('SP', $simulation->state);
        self::assertSame(SimulationType::REFINANCING, $simulation->type);
        self::assertSame('Refinancing', $simulation->typeRaw);
        self::assertNotNull($simulation->transaction);
        self::assertSame(78495.0, $simulation->transaction->totalCredit);
        self::assertSame(78496.0, $simulation->transaction->totalDebit);
        self::assertNotNull($simulation->interest);
        self::assertSame(3.29, $simulation->interest->monthlyRate);
        self::assertSame(39.48, $simulation->interest->annualRate);
        self::assertNotNull($simulation->cet);
        self::assertSame(3.62, $simulation->cet->monthlyRate);
        self::assertSame(43.44, $simulation->cet->annualRate);
        self::assertNotNull($simulation->iof);
        self::assertSame(872.0, $simulation->iof->amount);
        self::assertSame(1.92, $simulation->iof->rate);
        self::assertNotNull($simulation->vehicle);
        self::assertSame('Honda', $simulation->vehicle->brand);
        self::assertSame('Civic', $simulation->vehicle->model);
        self::assertSame(2024, $simulation->vehicle->year);
        self::assertSame(124654.0, $simulation->vehicle->value);
        self::assertSame('MWO-3910', $simulation->vehicle->licensePlate);
        self::assertFalse($simulation->vehicle->isZeroKm);
        self::assertSame('1Y5Ye4U8kydsr4828', $simulation->vehicle->chassis);
        self::assertSame('70502561697', $simulation->vehicle->renavam);
        self::assertSame('Prata', $simulation->vehicle->color);
        self::assertSame('123.456.789-01', $simulation->customerCpf);
        self::assertSame('2025-10-17T19:05:08.984Z', $simulation->createdAt);
    }

    public function testUnknownSimulationTypeFallsBackToUnknownAndKeepsRaw(): void
    {
        $wire = self::SIMULATION_WIRE;
        $wire['tipo'] = 'Leasing';
        $h = TestClientFactory::harness([TestClientFactory::json(200, $wire)]);

        $simulation = $h->client->simulations->get('bea8d060-f93d-48eb-b6c4-45f64483cdd9');

        self::assertSame(SimulationType::UNKNOWN, $simulation->type);
        self::assertSame('Leasing', $simulation->typeRaw);
    }

    public function testGetRejectsMalformedIdentifier(): void
    {
        $h = TestClientFactory::harness([]);

        try {
            $h->client->simulations->get('../../v2/proposal');
            self::fail('Esperava CredicarroValidationException.');
        } catch (CredicarroValidationException $e) {
            self::assertSame('Validation.Identifier.Invalid', $e->getErrorCode());
        }
    }

    public function testListMapsPagedResultAndSendsPaging(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, [
            'itens' => [self::SIMULATION_WIRE],
            'pagina' => self::PAGE_WIRE,
        ])]);

        $page = $h->client->simulations->list(new ListRequest(2, 20));

        self::assertSame('pagina_inicial=2&limite=20', $h->lastApiRequest()->getUri()->getQuery());
        self::assertSame('/integration/v1/proposal/simulation', $h->lastApiRequest()->getUri()->getPath());
        self::assertCount(1, $page->items);
        self::assertSame(24, $page->items[0]->term);
        self::assertTrue($page->hasNext);
        self::assertFalse($page->hasPrevious);
        self::assertSame(1, $page->page);
        self::assertSame(10, $page->pageSize);
        self::assertSame(25, $page->totalCount);
    }

    public function testListUsesDefaultPagingWhenNoRequestIsGiven(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, ['itens' => [], 'pagina' => self::PAGE_WIRE])]);

        $h->client->simulations->list();

        self::assertSame('pagina_inicial=1&limite=10', $h->lastApiRequest()->getUri()->getQuery());
    }

    /**
     * @dataProvider invalidPagingProvider
     */
    public function testListValidatesPagingLocally(int $page, int $size, string $code): void
    {
        $h = TestClientFactory::harness([]);

        try {
            $h->client->simulations->list(new ListRequest($page, $size));
            self::fail('Esperava CredicarroValidationException.');
        } catch (CredicarroValidationException $e) {
            self::assertSame($code, $e->getErrorCode());
            self::assertCount(0, $h->tokenRequests());
        }
    }

    /**
     * @return array<string, array{0: int, 1: int, 2: string}>
     */
    public static function invalidPagingProvider(): array
    {
        return [
            'page zero' => [0, 10, 'Validation.Paging.InvalidNumber'],
            'size zero' => [1, 0, 'Validation.Paging.InvalidSize'],
            'size above 100' => [1, 101, 'Validation.Paging.InvalidSize'],
        ];
    }

    public function testPageSizeBoundariesAreAccepted(): void
    {
        $h = TestClientFactory::harness([
            TestClientFactory::json(200, ['itens' => [], 'pagina' => self::PAGE_WIRE]),
            TestClientFactory::json(200, ['itens' => [], 'pagina' => self::PAGE_WIRE]),
        ]);

        $h->client->simulations->list(new ListRequest(1, 1));
        $h->client->simulations->list(new ListRequest(1, 100));

        self::assertCount(2, $h->apiRequests());
    }
}
