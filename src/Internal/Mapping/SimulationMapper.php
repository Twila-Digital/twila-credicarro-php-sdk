<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Mapping;

use Twila\Credicarro\Simulations\CreateFinancingSimulationRequest;
use Twila\Credicarro\Simulations\CreateRefinancingSimulationRequest;
use Twila\Credicarro\Simulations\FinancingRangeOption;
use Twila\Credicarro\Simulations\RefinancingRangeOption;
use Twila\Credicarro\Simulations\Simulation;
use Twila\Credicarro\Simulations\SimulationCet;
use Twila\Credicarro\Simulations\SimulationInterest;
use Twila\Credicarro\Simulations\SimulationIof;
use Twila\Credicarro\Simulations\SimulationOption;
use Twila\Credicarro\Simulations\SimulationTransaction;
use Twila\Credicarro\Simulations\SimulationType;
use Twila\Credicarro\Simulations\SimulationVehicle;

/**
 * @internal
 */
final class SimulationMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function financingRequestToWire(CreateFinancingSimulationRequest $request, bool $withStore): array
    {
        $wire = [
            'cpf' => $request->cpf,
            'placa' => $request->licensePlate,
            'valorVeiculo' => $request->vehicleAmount,
            'valorEntrada' => $request->downPayment,
            'uf' => $request->state,
            'isVeiculoZeroKm' => $request->isZeroKm,
        ];

        if ($withStore) {
            $wire['cnpjLoja'] = $request->storeCnpj;
        }

        return $wire;
    }

    /**
     * @return array<string, mixed>
     */
    public static function refinancingRequestToWire(CreateRefinancingSimulationRequest $request, bool $withStore): array
    {
        $wire = [
            'cpf' => $request->cpf,
            'placa' => $request->licensePlate,
            'valorSolicitado' => $request->requestedAmount,
        ];

        if ($withStore) {
            $wire['cnpjLoja'] = $request->storeCnpj;
        }

        return $wire;
    }

    /**
     * @param array<mixed> $wire
     * @return array<int, FinancingRangeOption>
     */
    public static function financingRangeFromWire(array $wire): array
    {
        $options = [];
        foreach (Wire::objects($wire, 'opcoes_por_prazo') as $item) {
            $options[] = new FinancingRangeOption(
                Wire::int($item, 'prazo'),
                Wire::float($item, 'valor_minimo_entrada')
            );
        }

        return $options;
    }

    /**
     * @param array<mixed> $wire
     * @return array<int, RefinancingRangeOption>
     */
    public static function refinancingRangeFromWire(array $wire): array
    {
        $options = [];
        foreach (Wire::objects($wire, 'opcoes_por_prazo') as $item) {
            $options[] = new RefinancingRangeOption(
                Wire::int($item, 'prazo'),
                Wire::float($item, 'valor_minimo_refinanciavel'),
                Wire::float($item, 'valor_maximo_refinanciavel')
            );
        }

        return $options;
    }

    /**
     * @param array<mixed> $wire
     * @return array<int, SimulationOption>
     */
    public static function optionsFromWire(array $wire): array
    {
        $options = [];
        foreach (Wire::objects($wire, 'simulacoes') as $item) {
            $options[] = new SimulationOption(
                Wire::string($item, 'id_simulacao'),
                Wire::float($item, 'valor_solicitado'),
                Wire::int($item, 'prazo'),
                Wire::float($item, 'valor_parcela'),
                Wire::float($item, 'valor_tac'),
                Wire::float($item, 'valor_boleto'),
                Wire::float($item, 'valor_seguro'),
                Wire::float($item, 'valor_iof'),
                Wire::float($item, 'percentual_iof'),
                Wire::float($item, 'valor_credito_total'),
                Wire::float($item, 'valor_divida_total'),
                Wire::float($item, 'percentual_cet_mensal'),
                Wire::float($item, 'percentual_cet_anual'),
                Wire::float($item, 'percentual_juros_mensal'),
                Wire::float($item, 'percentual_juros_anual'),
                Wire::nullableFloat($item, 'valor_retencao_diaria_aproximado')
            );
        }

        return $options;
    }

    /**
     * @param array<mixed> $wire
     */
    public static function simulationFromWire(array $wire): Simulation
    {
        $typeRaw = Wire::string($wire, 'tipo');
        $customer = Wire::object($wire, 'cliente');

        return new Simulation(
            Wire::string($wire, 'id_simulacao'),
            Wire::int($wire, 'prazo'),
            Wire::float($wire, 'valor_parcela'),
            Wire::float($wire, 'valor_solicitado'),
            Wire::float($wire, 'valor_entrada'),
            Wire::float($wire, 'valor_bem'),
            Wire::float($wire, 'valor_tac'),
            Wire::float($wire, 'taxa_juros_negociada'),
            Wire::float($wire, 'valor_registro'),
            Wire::string($wire, 'uf'),
            SimulationType::fromWireName($typeRaw),
            $typeRaw,
            self::transaction(Wire::object($wire, 'transacao')),
            self::interest(Wire::object($wire, 'juros')),
            self::cet(Wire::object($wire, 'cet')),
            self::iof(Wire::object($wire, 'iof')),
            self::vehicle(Wire::object($wire, 'veiculo')),
            $customer !== null ? Wire::nullableString($customer, 'cpf') : null,
            Wire::string($wire, 'data_criacao')
        );
    }

    /**
     * @param array<mixed>|null $wire
     */
    private static function transaction(?array $wire): ?SimulationTransaction
    {
        return $wire === null ? null : new SimulationTransaction(
            Wire::float($wire, 'valor_total_credito'),
            Wire::float($wire, 'valor_total_debito')
        );
    }

    /**
     * @param array<mixed>|null $wire
     */
    private static function interest(?array $wire): ?SimulationInterest
    {
        return $wire === null ? null : new SimulationInterest(
            Wire::float($wire, 'percentual_juros_mensal'),
            Wire::float($wire, 'percentual_juros_anual')
        );
    }

    /**
     * @param array<mixed>|null $wire
     */
    private static function cet(?array $wire): ?SimulationCet
    {
        return $wire === null ? null : new SimulationCet(
            Wire::float($wire, 'percentual_mensal'),
            Wire::float($wire, 'percentual_anual')
        );
    }

    /**
     * @param array<mixed>|null $wire
     */
    private static function iof(?array $wire): ?SimulationIof
    {
        return $wire === null ? null : new SimulationIof(
            Wire::float($wire, 'valor'),
            Wire::float($wire, 'percentual')
        );
    }

    /**
     * @param array<mixed>|null $wire
     */
    private static function vehicle(?array $wire): ?SimulationVehicle
    {
        return $wire === null ? null : new SimulationVehicle(
            Wire::string($wire, 'marca'),
            Wire::string($wire, 'modelo'),
            Wire::string($wire, 'versao'),
            Wire::nullableInt($wire, 'ano'),
            Wire::nullableFloat($wire, 'valor'),
            Wire::string($wire, 'placa'),
            Wire::nullableBool($wire, 'veiculo_zero_km'),
            Wire::string($wire, 'chassi'),
            Wire::string($wire, 'renavam'),
            Wire::string($wire, 'cor')
        );
    }
}
