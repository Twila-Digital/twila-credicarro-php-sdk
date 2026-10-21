<?php

declare(strict_types=1);

namespace Twila\Credicarro\Simulations;

final class FinancingRangeOption
{
    /** @var int prazo em meses (`prazo`) */
    public $term;

    /** @var float valor mínimo de entrada para o prazo (`valor_minimo_entrada`) */
    public $minimumDownPayment;

    public function __construct(int $term, float $minimumDownPayment)
    {
        $this->term = $term;
        $this->minimumDownPayment = $minimumDownPayment;
    }
}
