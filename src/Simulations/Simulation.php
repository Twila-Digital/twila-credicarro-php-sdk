<?php

declare(strict_types=1);

namespace Twila\Credicarro\Simulations;

use Twila\Credicarro\Internal\Masking\MasksSensitiveData;

/**
 * Simulação consultada (`GET v1/proposal/simulation/{id}` e listagem).
 */
final class Simulation
{
    use MasksSensitiveData;

    /** @var string `id_simulacao` */
    public $simulationId;

    /** @var int prazo (`prazo`) */
    public $term;

    /** @var float `valor_parcela` */
    public $installmentAmount;

    /** @var float `valor_solicitado` */
    public $requestedAmount;

    /** @var float `valor_entrada` */
    public $downPayment;

    /** @var float `valor_bem` */
    public $assetAmount;

    /** @var float `valor_tac` */
    public $tacAmount;

    /** @var float `taxa_juros_negociada` */
    public $negotiatedInterestRate;

    /** @var float `valor_registro` */
    public $registrationAmount;

    /** @var string `uf` */
    public $state;

    /** @var int um dos SimulationType::* */
    public $type;

    /** @var string valor bruto de `tipo` */
    public $typeRaw;

    /** @var SimulationTransaction|null `transacao` */
    public $transaction;

    /** @var SimulationInterest|null `juros` */
    public $interest;

    /** @var SimulationCet|null `cet` */
    public $cet;

    /** @var SimulationIof|null `iof` */
    public $iof;

    /** @var SimulationVehicle|null `veiculo` */
    public $vehicle;

    /** @var string|null `cliente.cpf` */
    public $customerCpf;

    /** @var string `data_criacao` (ISO-8601) */
    public $createdAt;

    public function __construct(
        string $simulationId,
        int $term,
        float $installmentAmount,
        float $requestedAmount,
        float $downPayment,
        float $assetAmount,
        float $tacAmount,
        float $negotiatedInterestRate,
        float $registrationAmount,
        string $state,
        int $type,
        string $typeRaw,
        ?SimulationTransaction $transaction,
        ?SimulationInterest $interest,
        ?SimulationCet $cet,
        ?SimulationIof $iof,
        ?SimulationVehicle $vehicle,
        ?string $customerCpf,
        string $createdAt
    ) {
        $this->simulationId = $simulationId;
        $this->term = $term;
        $this->installmentAmount = $installmentAmount;
        $this->requestedAmount = $requestedAmount;
        $this->downPayment = $downPayment;
        $this->assetAmount = $assetAmount;
        $this->tacAmount = $tacAmount;
        $this->negotiatedInterestRate = $negotiatedInterestRate;
        $this->registrationAmount = $registrationAmount;
        $this->state = $state;
        $this->type = $type;
        $this->typeRaw = $typeRaw;
        $this->transaction = $transaction;
        $this->interest = $interest;
        $this->cet = $cet;
        $this->iof = $iof;
        $this->vehicle = $vehicle;
        $this->customerCpf = $customerCpf;
        $this->createdAt = $createdAt;
    }

    /**
     * @return array<string, string>
     */
    protected function sensitiveFields(): array
    {
        return [
            'customerCpf' => 'cpf',
        ];
    }
}
