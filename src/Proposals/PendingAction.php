<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

/**
 * Ação que o seu sistema precisa executar para a proposta avançar.
 */
final class PendingAction
{
    /** @var string um dos ProposalParticipant::* */
    public $participant;

    /** @var int um dos ProposalStep::* */
    public $step;

    /** @var string um dos PendingActionKind::* */
    public $kind;

    /** @var array<int, int> códigos de DocumentType (vazio para dados do veículo e conta de recebimento) */
    public $documentTypes;

    /**
     * @param array<int, int> $documentTypes
     */
    public function __construct(string $participant, int $step, string $kind, array $documentTypes = [])
    {
        $this->participant = $participant;
        $this->step = $step;
        $this->kind = $kind;
        $this->documentTypes = $documentTypes;
    }
}
