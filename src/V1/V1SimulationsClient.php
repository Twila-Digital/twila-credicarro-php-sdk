<?php

declare(strict_types=1);

namespace Twila\Credicarro\V1;

use Twila\Credicarro\Internal\Http\ApiRequestExecutor;
use Twila\Credicarro\Internal\Http\QueryStringBuilder;
use Twila\Credicarro\Internal\Http\ResponseReader;
use Twila\Credicarro\Internal\Mapping\SimulationMapper;
use Twila\Credicarro\Internal\Validation;
use Twila\Credicarro\Simulations\CreateFinancingSimulationRequest;
use Twila\Credicarro\Simulations\CreateRefinancingSimulationRequest;
use Twila\Credicarro\Simulations\FinancingRangeOption;
use Twila\Credicarro\Simulations\RefinancingRangeOption;
use Twila\Credicarro\Simulations\SimulationOption;

/**
 * Rotas v1 de simulação, sem `cnpjLoja` (a loja de integração é a única vinculada ao parceiro). Prefira
 * `$client->simulations` (v2) quando o parceiro tiver mais de uma loja. `storeCnpj` das requisições é ignorado aqui.
 */
final class V1SimulationsClient
{
    /** @var ApiRequestExecutor */
    private $executor;

    public function __construct(ApiRequestExecutor $executor)
    {
        $this->executor = $executor;
    }

    /**
     * `GET v1/proposal/simulation/range/financing?placa=`.
     *
     * @return array<int, FinancingRangeOption>
     */
    public function getFinancingRange(string $licensePlate): array
    {
        $response = $this->executor->get($this->rangePath('financing', $licensePlate));
        ApiRequestExecutor::ensureSuccess($response);

        return SimulationMapper::financingRangeFromWire(ResponseReader::object($response));
    }

    /**
     * `GET v1/proposal/simulation/range/refinancing?placa=`.
     *
     * @return array<int, RefinancingRangeOption>
     */
    public function getRefinancingRange(string $licensePlate): array
    {
        $response = $this->executor->get($this->rangePath('refinancing', $licensePlate));
        ApiRequestExecutor::ensureSuccess($response);

        return SimulationMapper::refinancingRangeFromWire(ResponseReader::object($response));
    }

    /**
     * `POST v1/proposal/simulation/financing`. Recebe Idempotency-Key automática.
     *
     * @return array<int, SimulationOption>
     */
    public function createFinancing(CreateFinancingSimulationRequest $request): array
    {
        $response = $this->executor->post(
            'v1/proposal/simulation/financing',
            SimulationMapper::financingRequestToWire($request, false)
        );
        ApiRequestExecutor::ensureSuccess($response);

        return SimulationMapper::optionsFromWire(ResponseReader::object($response));
    }

    /**
     * `POST v1/proposal/simulation/refinancing`. Recebe Idempotency-Key automática.
     *
     * @return array<int, SimulationOption>
     */
    public function createRefinancing(CreateRefinancingSimulationRequest $request): array
    {
        $response = $this->executor->post(
            'v1/proposal/simulation/refinancing',
            SimulationMapper::refinancingRequestToWire($request, false)
        );
        ApiRequestExecutor::ensureSuccess($response);

        return SimulationMapper::optionsFromWire(ResponseReader::object($response));
    }

    private function rangePath(string $kind, string $licensePlate): string
    {
        return (new QueryStringBuilder())
            ->add('placa', Validation::requireNotBlank($licensePlate, 'placa'))
            ->build('v1/proposal/simulation/range/' . $kind);
    }
}
