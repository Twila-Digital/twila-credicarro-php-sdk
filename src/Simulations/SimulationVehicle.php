<?php

declare(strict_types=1);

namespace Twila\Credicarro\Simulations;

final class SimulationVehicle
{
    /** @var string `marca` */
    public $brand;

    /** @var string `modelo` */
    public $model;

    /** @var string `versao` */
    public $version;

    /** @var int|null `ano` */
    public $year;

    /** @var float|null `valor` */
    public $value;

    /** @var string `placa` */
    public $licensePlate;

    /** @var bool|null `veiculo_zero_km` */
    public $isZeroKm;

    /** @var string `chassi` */
    public $chassis;

    /** @var string `renavam` */
    public $renavam;

    /** @var string `cor` */
    public $color;

    public function __construct(
        string $brand,
        string $model,
        string $version,
        ?int $year,
        ?float $value,
        string $licensePlate,
        ?bool $isZeroKm,
        string $chassis,
        string $renavam,
        string $color
    ) {
        $this->brand = $brand;
        $this->model = $model;
        $this->version = $version;
        $this->year = $year;
        $this->value = $value;
        $this->licensePlate = $licensePlate;
        $this->isZeroKm = $isZeroKm;
        $this->chassis = $chassis;
        $this->renavam = $renavam;
        $this->color = $color;
    }
}
