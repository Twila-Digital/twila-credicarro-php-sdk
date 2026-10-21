<?php

declare(strict_types=1);

namespace Twila\Credicarro\Simulations;

final class SimulationCet
{
    /** @var float `percentual_mensal` */
    public $monthlyRate;

    /** @var float `percentual_anual` */
    public $annualRate;

    public function __construct(float $monthlyRate, float $annualRate)
    {
        $this->monthlyRate = $monthlyRate;
        $this->annualRate = $annualRate;
    }
}
