<?php

declare(strict_types=1);

namespace Twila\Credicarro\Simulations;

use Twila\Credicarro\Internal\Masking\MasksSensitiveData;

final class CreateRefinancingSimulationRequest
{
    use MasksSensitiveData;

    /** @var string CPF do cliente, já cadastrado, com ou sem pontuação (`cpf`) */
    public $cpf;

    /** @var string placa antiga (ABC-1234) ou Mercosul (ABC1D23) (`placa`) */
    public $licensePlate;

    /** @var float valor solicitado, dentro da faixa refinanciável da placa (`valorSolicitado`) */
    public $requestedAmount;

    /**
     * CNPJ da loja de integração vinculada ao parceiro (`cnpjLoja`). Obrigatório em `$client->simulations`
     * (v2); ignorado em `$client->v1->simulations`, que não tem esse campo.
     *
     * @var string|null
     */
    public $storeCnpj;

    /**
     * Retorno de comissão a aplicar, dentro da faixa configurada na loja (`retornoComissao`). Opcional: `null`
     * usa a taxa padrão, quando a loja permitir. Consulte a faixa aceita com `SimulationsClient::getCommissionReturns`.
     * Ignorado em `$client->v1->simulations`, que não tem esse campo.
     *
     * @var int|null
     */
    public $commissionReturn;

    public function __construct(
        #[\SensitiveParameter]
        string $cpf,
        string $licensePlate,
        float $requestedAmount,
        ?string $storeCnpj = null,
        ?int $commissionReturn = null
    ) {
        $this->cpf = $cpf;
        $this->licensePlate = $licensePlate;
        $this->requestedAmount = $requestedAmount;
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
