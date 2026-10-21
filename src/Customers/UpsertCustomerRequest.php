<?php

declare(strict_types=1);

namespace Twila\Credicarro\Customers;

use Twila\Credicarro\Internal\Masking\MasksSensitiveData;

final class UpsertCustomerRequest
{
    use MasksSensitiveData;

    /** @var string CPF com ou sem pontuação (`cpf`) */
    public $cpf;

    /** @var string primeiro nome (`nome`), até 100 caracteres */
    public $firstName;

    /** @var string sobrenome (`sobrenome`), até 100 caracteres */
    public $lastName;

    /** @var string e-mail (`email`), até 254 caracteres */
    public $email;

    /** @var string celular exatamente no formato `+55 (DD) 99999-9999` (`celular`) */
    public $phoneNumber;

    /** @var string data de nascimento ISO-8601 com offset (`dataDeNascimento`), cliente entre 18 e 75 anos */
    public $dateOfBirth;

    /** @var CustomerAddress */
    public $address;

    /** @var ProfessionalData */
    public $professionalData;

    public function __construct(
        #[\SensitiveParameter]
        string $cpf,
        string $firstName,
        string $lastName,
        string $email,
        string $phoneNumber,
        string $dateOfBirth,
        CustomerAddress $address,
        ProfessionalData $professionalData
    ) {
        $this->cpf = $cpf;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->phoneNumber = $phoneNumber;
        $this->dateOfBirth = $dateOfBirth;
        $this->address = $address;
        $this->professionalData = $professionalData;
    }

    /**
     * @return array<string, string>
     */
    protected function sensitiveFields(): array
    {
        return [
            'cpf' => 'cpf',
            'email' => 'full',
            'phoneNumber' => 'full',
            'dateOfBirth' => 'full',
        ];
    }
}
