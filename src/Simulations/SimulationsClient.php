<?php

declare(strict_types=1);

namespace Twila\Credicarro\Simulations;

use Twila\Credicarro\Internal\Http\ApiRequestExecutor;
use Twila\Credicarro\Internal\Http\QueryStringBuilder;
use Twila\Credicarro\Internal\Http\ResponseReader;
use Twila\Credicarro\Internal\Mapping\PagedMapper;
use Twila\Credicarro\Internal\Mapping\SimulationMapper;
use Twila\Credicarro\Internal\Validation;
use Twila\Credicarro\ListRequest;
use Twila\Credicarro\PagedResult;

/**
 * Simulações. As consultas de faixa e a criação usam a v2 (loja identificada por `cnpjLoja`); `get` e `list`
 * leem as simulações pela v1. A v1 sem `cnpjLoja` fica em `$client->v1->simulations`.
 */
final class SimulationsClient
{
    /** @var ApiRequestExecutor */
    private $executor;

    public function __construct(ApiRequestExecutor $executor)
    {
        $this->executor = $executor;
    }

    /**
     * Valor mínimo de entrada por prazo para a placa (`GET v2/proposal/simulation/range/financing`).
     *
     * @return array<int, FinancingRangeOption>
     */
    public function getFinancingRange(string $licensePlate, string $storeCnpj): array
    {
        $response = $this->executor->get($this->rangePath('financing', $licensePlate, $storeCnpj));
        ApiRequestExecutor::ensureSuccess($response);

        return SimulationMapper::financingRangeFromWire(ResponseReader::object($response));
    }

    /**
     * Valor mínimo e máximo refinanciável por prazo para a placa (`GET v2/proposal/simulation/range/refinancing`).
     *
     * @return array<int, RefinancingRangeOption>
     */
    public function getRefinancingRange(string $licensePlate, string $storeCnpj): array
    {
        $response = $this->executor->get($this->rangePath('refinancing', $licensePlate, $storeCnpj));
        ApiRequestExecutor::ensureSuccess($response);

        return SimulationMapper::refinancingRangeFromWire(ResponseReader::object($response));
    }

    /**
     * Cria a simulação de financiamento (`POST v2/proposal/simulation/financing`), uma opção por prazo.
     * O cliente precisa estar cadastrado. `storeCnpj` é obrigatório. Recebe Idempotency-Key automática.
     *
     * @return array<int, SimulationOption>
     */
    public function createFinancing(CreateFinancingSimulationRequest $request): array
    {
        Validation::requireNotBlank($request->storeCnpj, 'cnpjLoja');

        $response = $this->executor->post(
            'v2/proposal/simulation/financing',
            SimulationMapper::financingRequestToWire($request, true)
        );
        ApiRequestExecutor::ensureSuccess($response);

        return SimulationMapper::optionsFromWire(ResponseReader::object($response));
    }

    /**
     * Cria a simulação de refinanciamento (`POST v2/proposal/simulation/refinancing`), uma opção por prazo.
     * O cliente precisa estar cadastrado com endereço. `storeCnpj` é obrigatório. Recebe Idempotency-Key automática.
     *
     * @return array<int, SimulationOption>
     */
    public function createRefinancing(CreateRefinancingSimulationRequest $request): array
    {
        Validation::requireNotBlank($request->storeCnpj, 'cnpjLoja');

        $response = $this->executor->post(
            'v2/proposal/simulation/refinancing',
            SimulationMapper::refinancingRequestToWire($request, true)
        );
        ApiRequestExecutor::ensureSuccess($response);

        return SimulationMapper::optionsFromWire(ResponseReader::object($response));
    }

    /**
     * Consulta uma simulação pelo ID (`GET v1/proposal/simulation/{id}`).
     */
    public function get(string $simulationId): Simulation
    {
        $id = Validation::requireUuid($simulationId, 'simulacaoId');

        $response = $this->executor->get('v1/proposal/simulation/' . rawurlencode($id));
        ApiRequestExecutor::ensureSuccess($response);

        return SimulationMapper::simulationFromWire(ResponseReader::object($response));
    }

    /**
     * Lista as simulações de forma paginada (`GET v1/proposal/simulation`). Os itens são `Simulation`.
     */
    public function list(?ListRequest $request = null): PagedResult
    {
        $response = $this->executor->get(PagedMapper::path('v1/proposal/simulation', $request));
        ApiRequestExecutor::ensureSuccess($response);

        return PagedMapper::fromWire($response->body, [SimulationMapper::class, 'simulationFromWire']);
    }

    private function rangePath(string $kind, string $licensePlate, string $storeCnpj): string
    {
        return (new QueryStringBuilder())
            ->add('placa', Validation::requireNotBlank($licensePlate, 'placa'))
            ->add('cnpjLoja', Validation::requireNotBlank($storeCnpj, 'cnpjLoja'))
            ->build('v2/proposal/simulation/range/' . $kind);
    }
}
