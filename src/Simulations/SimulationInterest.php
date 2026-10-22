<?php

declare(strict_types=1);

namespace Twila\Credicarro\Simulations;

final class SimulationInterest
{
    /** @var float `percentual_juros_mensal` */
    public $monthlyRate;

    /** @var float `percentual_juros_anual` */
    public $annualRate;

    public function __construct(float $monthlyRate, float $annualRate)
    {
        $this->monthlyRate = $monthlyRate;
        $this->annualRate = $annualRate;
    }
}
