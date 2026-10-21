<?php

declare(strict_types=1);

namespace Twila\Credicarro\Customers;

final class CustomerAddress
{
    /** @var string rua (`rua`), até 200 caracteres */
    public $street;

    /** @var string bairro (`bairro`), até 100 caracteres */
    public $neighborhood;

    /** @var string cidade (`cidade`), até 100 caracteres */
    public $city;

    /** @var string sigla da UF (`estado`) */
    public $state;

    /** @var string CEP com 8 dígitos, com ou sem hífen (`cep`) */
    public $postalCode;

    /** @var string|null número (`numero`), opcional, até 20 caracteres */
    public $number;

    /** @var string|null complemento (`complemento`), opcional, até 100 caracteres */
    public $complement;

    public function __construct(
        string $street,
        string $neighborhood,
        string $city,
        string $state,
        string $postalCode,
        ?string $number = null,
        ?string $complement = null
    ) {
        $this->street = $street;
        $this->neighborhood = $neighborhood;
        $this->city = $city;
        $this->state = $state;
        $this->postalCode = $postalCode;
        $this->number = $number;
        $this->complement = $complement;
    }
}
