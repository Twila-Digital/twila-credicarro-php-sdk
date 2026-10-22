<?php

declare(strict_types=1);

namespace Twila\Credicarro\Customers;

use Twila\Credicarro\Internal\Http\ApiRequestExecutor;
use Twila\Credicarro\Internal\Http\ResponseReader;
use Twila\Credicarro\Internal\Mapping\CustomerMapper;
use Twila\Credicarro\Internal\Validation;

/**
 * Cadastro do cliente (comprador). O cliente é identificado pelo CPF em todas as chamadas.
 */
final class CustomersClient
{
    /** @var ApiRequestExecutor */
    private $executor;

    public function __construct(ApiRequestExecutor $executor)
    {
        $this->executor = $executor;
    }

    /**
     * Cadastro completo em uma chamada (`POST v1/customer/unified`) — recomendado. Devolve o `id_cliente`.
     *
     * ATENÇÃO: sem `$idempotencyKey` a rota é um upsert (cria ou atualiza o cadastro do CPF) e o SDK NÃO gera chave
     * automática nem repete a chamada em caso de falha. Com `$idempotencyKey` a rota só cria (CPF já cadastrado
     * devolve 409 `Cpf.CpfAlreadyAdded`) e a chamada passa a ser segura para repetição, com a mesma chave.
     */
    public function upsert(UpsertCustomerRequest $request, ?string $idempotencyKey = null): string
    {
        $key = Validation::requireIdempotencyKey($idempotencyKey);

        $response = $this->executor->post(
            'v1/customer/unified',
            CustomerMapper::upsertRequestToWire($request),
            $key
        );
        ApiRequestExecutor::ensureSuccess($response);

        return ResponseReader::requiredString($response, 'id_cliente');
    }

    /**
     * Cria o cliente só com os dados pessoais (`POST v1/customer`). CPF, e-mail ou celular já cadastrados devolvem 409.
     * Recebe Idempotency-Key automática. Devolve o `id_cliente`.
     */
    public function create(CreateCustomerRequest $request): string
    {
        $response = $this->executor->post('v1/customer', CustomerMapper::createRequestToWire($request));
        ApiRequestExecutor::ensureSuccess($response);

        return ResponseReader::requiredString($response, 'id_cliente');
    }

    /**
     * Cria ou atualiza o endereço de um cliente existente (`POST v1/customer/address`, resposta 202 sem corpo).
     */
    public function upsertAddress(
        #[\SensitiveParameter]
        string $cpf,
        CustomerAddress $address
    ): void {
        $response = $this->executor->post(
            'v1/customer/address',
            CustomerMapper::addressRequestToWire(Validation::requireNotBlank($cpf, 'cpf'), $address)
        );
        ApiRequestExecutor::ensureSuccess($response);
    }

    /**
     * Cria ou atualiza ocupação e renda de um cliente existente (`POST v1/customer/professional`, 202 sem corpo).
     */
    public function upsertProfessionalData(
        #[\SensitiveParameter]
        string $cpf,
        ProfessionalData $data
    ): void {
        $response = $this->executor->post(
            'v1/customer/professional',
            CustomerMapper::professionalRequestToWire(Validation::requireNotBlank($cpf, 'cpf'), $data)
        );
        ApiRequestExecutor::ensureSuccess($response);
    }
}
