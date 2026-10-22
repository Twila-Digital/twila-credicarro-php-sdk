<?php

declare(strict_types=1);

namespace Twila\Credicarro\Simulations;

/**
 * Faixa de retorno de comissão configurada para a loja, por tipo de simulação
 * (`GET v2/proposal/simulation/commission-returns`).
 */
final class CommissionReturn
{
    /** @var int tipo de simulação ao qual o retorno se aplica, ver {@see CommissionReturnType} (`tipo`) */
    public $type;

    /** @var int a quem pertence a configuração de comissão, ver {@see CommissionReturnTarget} (`alvo`) */
    public $target;

    /** @var int menor retorno de comissão aceito para esse tipo (`retorno_minimo`) */
    public $minimumReturn;

    /** @var int maior retorno de comissão aceito para esse tipo (`retorno_maximo`) */
    public $maximumReturn;

    /** @var bool indica se a loja aceita simular sem informar `commissionReturn` (`permite_taxa_padrao`) */
    public $allowDefaultRate;

    public function __construct(int $type, int $target, int $minimumReturn, int $maximumReturn, bool $allowDefaultRate)
    {
        $this->type = $type;
        $this->target = $target;
        $this->minimumReturn = $minimumReturn;
        $this->maximumReturn = $maximumReturn;
        $this->allowDefaultRate = $allowDefaultRate;
    }
}
