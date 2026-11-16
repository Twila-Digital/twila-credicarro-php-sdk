<?php

declare(strict_types=1);

namespace Twila\Credicarro\Simulations;

use Twila\Credicarro\Internal\Masking\MasksSensitiveData;

final class CreateFinancingSimulationRequest
{
    use MasksSensitiveData;

    /** @var string CPF do cliente, já cadastrado, com ou sem pontuação (`cpf`) */
    public $cpf;

    /** @var string placa antiga (ABC-1234) ou Mercosul (ABC1D23) (`placa`) */
    public $licensePlate;

    /** @var float valor total do veículo, maior que zero (`valorVeiculo`) */
    public $vehicleAmount;

    /** @var float valor de entrada; zero quando não houver, menor que o valor do veículo (`valorEntrada`) */
    public $downPayment;

    /** @var string UF do registro do veículo, 2 letras (`uf`) */
    public $state;

    /** @var bool veículo zero quilômetro (`isVeiculoZeroKm`) */
    public $isZeroKm;

    /**
     * CNPJ da loja de integração vinculada ao parceiro (`cnpjLoja`). Obrigatório em `$client->simulations`
     * (v2); ignorado em `$client->v1->simulations`, que não tem esse campo.
     *
     * @var string|null
     */
    public $storeCnpj;

    /**
     * Retorno de comissão (`retornoComissao`). Opcional; quando informado, precisa estar na faixa `Financing` da
     * loja (veja `SimulationsClient::getCommissionReturns`). Só é enviado na v2; `null` não é enviado.
     *
     * @var int|null
     */
    public $commissionReturn;

    public function __construct(
        #[\SensitiveParameter]
        string $cpf,
        string $licensePlate,
        float $vehicleAmount,
        float $downPayment,
        string $state,
        bool $isZeroKm,
        ?string $storeCnpj = null,
        ?int $commissionReturn = null
    ) {
        $this->cpf = $cpf;
        $this->licensePlate = $licensePlate;
        $this->vehicleAmount = $vehicleAmount;
        $this->downPayment = $downPayment;
        $this->state = $state;
        $this->isZeroKm = $isZeroKm;
        $this->storeCnpj = $storeCnpj;
        $this->commissionReturn = $commissionReturn;
    }

    /**
     * @return array<string, string>
     */
    protected function sensitiveFields(): array
    {
        return [
            'cpf' => 'cpf',
        ];
    }
}
