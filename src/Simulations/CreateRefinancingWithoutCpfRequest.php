<?php

declare(strict_types=1);

namespace Twila\Credicarro\Simulations;

final class CreateRefinancingWithoutCpfRequest
{
    /** @var string placa antiga (ABC-1234) ou Mercosul (ABC1D23) (`placa`) */
    public $licensePlate;

    /** @var float valor solicitado, maior que zero e dentro da faixa refinanciável da placa (`valorSolicitado`) */
    public $requestedAmount;

    /** @var string UF do registro do veículo, 2 letras; define as taxas e o custo de registro (`uf`) */
    public $state;

    public function __construct(string $licensePlate, float $requestedAmount, string $state)
    {
        $this->licensePlate = $licensePlate;
        $this->requestedAmount = $requestedAmount;
        $this->state = $state;
    }
}
