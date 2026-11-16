<?php

declare(strict_types=1);

namespace Twila\Credicarro\Simulations;

/**
 * Faixa de retorno de comissão configurada para a loja. A simulação valida pela faixa com `target`
 * `CommissionReturnTarget::STORE`.
 */
final class CommissionReturn
{
    /** @var int um dos SimulationType::* (`tipo`: Financing ou Refinancing) */
    public $type;

    /** @var string|null `tipo` (texto original) */
    public $typeRaw;

    /** @var int um dos CommissionReturnTarget::* (`alvo`) */
    public $target;

    /** @var string|null `alvo` (texto original) */
    public $targetRaw;

    /** @var int menor `commissionReturn` aceito (`retorno_minimo`) */
    public $minimumReturn;

    /** @var int maior `commissionReturn` aceito (`retorno_maximo`) */
    public $maximumReturn;

    /** @var bool a loja permite usar a taxa padrão (`permite_taxa_padrao`) */
    public $allowsDefaultRate;

    public function __construct(
        int $type,
        ?string $typeRaw,
        int $target,
        ?string $targetRaw,
        int $minimumReturn,
        int $maximumReturn,
        bool $allowsDefaultRate
    ) {
        $this->type = $type;
        $this->typeRaw = $typeRaw;
        $this->target = $target;
        $this->targetRaw = $targetRaw;
        $this->minimumReturn = $minimumReturn;
        $this->maximumReturn = $maximumReturn;
        $this->allowsDefaultRate = $allowsDefaultRate;
    }
}
