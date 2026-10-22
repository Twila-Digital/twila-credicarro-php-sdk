<?php

declare(strict_types=1);

namespace Twila\Credicarro\Simulations;

final class SimulationIof
{
    /** @var float `valor` */
    public $amount;

    /** @var float `percentual` */
    public $rate;

    public function __construct(float $amount, float $rate)
    {
        $this->amount = $amount;
        $this->rate = $rate;
    }
}
