<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

final class VehicleDetails
{
    /** @var string Renavam, até 11 dígitos, com dígito verificador válido (`renavam`) */
    public $renavam;

    /** @var string chassi com 17 caracteres, sem as letras I, O e Q (`chassi`) */
    public $chassis;

    /** @var string placa: ABC-1234, ABC1234 ou ABC1D23 (`placa`) */
    public $licensePlate;

    /** @var string cor, sem números, até 30 caracteres (`cor`) */
    public $color;

    public function __construct(string $renavam, string $chassis, string $licensePlate, string $color)
    {
        $this->renavam = $renavam;
        $this->chassis = $chassis;
        $this->licensePlate = $licensePlate;
        $this->color = $color;
    }
}
