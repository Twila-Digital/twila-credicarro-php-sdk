<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

/**
 * Etapa em que o participante está agora, a situação dela e quem precisa agir (`status_ativo`).
 */
final class ActiveStep
{
    /** @var int um dos ProposalStep::* */
    public $step;

    /** @var int|null `enum_etapa` */
    public $stepRaw;

    /** @var string|null `etapa` */
    public $stepName;

    /** @var int um dos StepSituation::* */
    public $situation;

    /** @var int|null `enum_situacao` */
    public $situationRaw;

    /** @var string|null `situacao` */
    public $situationName;

    /** @var int um dos ActionOwner::* */
    public $actionOwner;

    /** @var int|null `enum_responsavel_acao` */
    public $actionOwnerRaw;

    /** @var string|null `responsavel_acao` */
    public $actionOwnerName;

    public function __construct(
        int $step,
        ?int $stepRaw,
        ?string $stepName,
        int $situation,
        ?int $situationRaw,
        ?string $situationName,
        int $actionOwner,
        ?int $actionOwnerRaw,
        ?string $actionOwnerName
    ) {
        $this->step = $step;
        $this->stepRaw = $stepRaw;
        $this->stepName = $stepName;
        $this->situation = $situation;
        $this->situationRaw = $situationRaw;
        $this->situationName = $situationName;
        $this->actionOwner = $actionOwner;
        $this->actionOwnerRaw = $actionOwnerRaw;
        $this->actionOwnerName = $actionOwnerName;
    }
}
