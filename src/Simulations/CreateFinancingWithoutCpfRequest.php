<?php

declare(strict_types=1);

namespace Twila\Credicarro\Simulations;

final class CreateFinancingWithoutCpfRequest
{
    /** @var string placa antiga (ABC-1234) ou Mercosul (ABC1D23) (`placa`) */
    public $licensePlate;

    /** @var float valor total do veículo, maior que zero (`valorVeiculo`) */
    public $vehicleAmount;

    /** @var string UF do registro do veículo, 2 letras (`uf`) */
    public $state;

    /**
     * Valor de entrada (`valorEntrada`). Quando `null`, o campo não é enviado e a API usa a entrada mínima da taxa da loja.
     *
     * @var float|null
     */
    public $downPayment;

    public function __construct(string $licensePlate, float $vehicleAmount, string $state, ?float $downPayment = null)
    {
        $this->licensePlate = $licensePlate;
        $this->vehicleAmount = $vehicleAmount;
        $this->state = $state;
        $this->downPayment = $downPayment;
    }
}
