<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Mapping;

use Twila\Credicarro\Customers\CreateCustomerRequest;
use Twila\Credicarro\Customers\CustomerAddress;
use Twila\Credicarro\Customers\ProfessionalData;
use Twila\Credicarro\Customers\UpsertCustomerRequest;

/**
 * @internal
 */
final class CustomerMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function upsertRequestToWire(UpsertCustomerRequest $request): array
    {
        return [
            'cpf' => $request->cpf,
            'nome' => $request->firstName,
            'sobrenome' => $request->lastName,
            'email' => $request->email,
            'celular' => $request->phoneNumber,
            'dataDeNascimento' => $request->dateOfBirth,
            'endereco' => self::addressToWire($request->address),
            'dadosProfissionais' => self::professionalDataToWire($request->professionalData),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function createRequestToWire(CreateCustomerRequest $request): array
    {
        return [
            'cpf' => $request->cpf,
            'nome' => $request->firstName,
            'sobrenome' => $request->lastName,
            'email' => $request->email,
            'celular' => $request->phoneNumber,
            'dataDeNascimento' => $request->dateOfBirth,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function addressRequestToWire(
        #[\SensitiveParameter]
        string $cpf,
        CustomerAddress $address
    ): array {
        return [
            'cpf' => $cpf,
            'endereco' => self::addressToWire($address),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function professionalRequestToWire(
        #[\SensitiveParameter]
        string $cpf,
        ProfessionalData $data
    ): array {
        return ['cpf' => $cpf] + self::professionalDataToWire($data);
    }

    /**
     * @return array<string, mixed>
     */
    private static function addressToWire(CustomerAddress $address): array
    {
        return Wire::withoutNulls([
            'rua' => $address->street,
            'numero' => $address->number,
            'complemento' => $address->complement,
            'bairro' => $address->neighborhood,
            'cidade' => $address->city,
            'estado' => $address->state,
            'cep' => $address->postalCode,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function professionalDataToWire(ProfessionalData $data): array
    {
        return [
            'tipoOcupacao' => $data->occupation,
            'renda' => $data->monthlyIncome,
        ];
    }
}
