<?php

declare(strict_types=1);

namespace Twila\Credicarro\Simulations;

final class SimulationTransaction
{
    /** @var float `valor_total_credito` */
    public $totalCredit;

    /** @var float `valor_total_debito` */
    public $totalDebit;

    public function __construct(float $totalCredit, float $totalDebit)
    {
        $this->totalCredit = $totalCredit;
        $this->totalDebit = $totalDebit;
    }
}
