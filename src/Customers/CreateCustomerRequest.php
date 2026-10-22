<?php

declare(strict_types=1);

namespace Twila\Credicarro\Customers;

use Twila\Credicarro\Internal\Masking\MasksSensitiveData;

final class CreateCustomerRequest
{
    use MasksSensitiveData;

    /** @var string */
    public $cpf;

    /** @var string */
    public $firstName;

    /** @var string */
    public $lastName;

    /** @var string */
    public $email;

    /** @var string celular exatamente no formato `+55 (DD) 99999-9999` */
    public $phoneNumber;

    /** @var string data de nascimento ISO-8601 com offset */
    public $dateOfBirth;

    public function __construct(
        #[\SensitiveParameter]
        string $cpf,
        string $firstName,
        string $lastName,
        string $email,
        string $phoneNumber,
        string $dateOfBirth
    ) {
        $this->cpf = $cpf;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->phoneNumber = $phoneNumber;
        $this->dateOfBirth = $dateOfBirth;
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
