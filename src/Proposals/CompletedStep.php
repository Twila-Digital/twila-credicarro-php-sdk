<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

/**
 * Etapa já concluída (`etapas_concluidas[]`), com a data da última passagem.
 */
final class CompletedStep
{
    /** @var int um dos ProposalStep::* */
    public $step;

    /** @var int|null `enum_etapa` */
    public $stepRaw;

    /** @var string|null `etapa` */
    public $stepName;

    /** @var string|null `data` (ISO-8601) */
    public $date;

    public function __construct(int $step, ?int $stepRaw, ?string $stepName, ?string $date)
    {
        $this->step = $step;
        $this->stepRaw = $stepRaw;
        $this->stepName = $stepName;
        $this->date = $date;
    }
}
