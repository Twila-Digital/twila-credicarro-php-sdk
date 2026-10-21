<?php

declare(strict_types=1);

namespace Twila\Credicarro\Simulations;

final class RefinancingRangeOption
{
    /** @var int prazo em meses (`prazo`) */
    public $term;

    /** @var float valor mínimo refinanciável (`valor_minimo_refinanciavel`) */
    public $minimumAmount;

    /** @var float valor máximo refinanciável (`valor_maximo_refinanciavel`) */
    public $maximumAmount;

    public function __construct(int $term, float $minimumAmount, float $maximumAmount)
    {
        $this->term = $term;
        $this->minimumAmount = $minimumAmount;
        $this->maximumAmount = $maximumAmount;
    }
}
