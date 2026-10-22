<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

/**
 * Retrato atual da proposta (`GET v2/proposal/{id}`).
 */
final class ProposalDetails
{
    /** @var string `id_proposta` */
    public $id;

    /** @var int `numero` */
    public $number;

    /** @var string `id_simulacao` */
    public $simulationId;

    /** @var int um dos ProposalStatus::* */
    public $status;

    /** @var int|null `enum_status` */
    public $statusRaw;

    /** @var string|null `status` */
    public $statusName;

    /** @var int um dos ProposalType::* */
    public $type;

    /** @var string|null `tipo` */
    public $typeRaw;

    /** @var string `data_criacao` (ISO-8601) */
    public $createdAt;

    /** @var string|null `motivo_recusa` */
    public $refusalReason;

    /** @var string|null `link_biometria` — link para o cliente fazer a biometria */
    public $biometryLink;

    /** @var ProposalParticipantState|null `comprador` */
    public $buyer;

    /** @var ProposalParticipantState|null `vendedor` */
    public $seller;

    /** @var ProposalParticipantState|null `loja` */
    public $store;

    public function __construct(
        string $id,
        int $number,
        string $simulationId,
        int $status,
        ?int $statusRaw,
        ?string $statusName,
        int $type,
        ?string $typeRaw,
        string $createdAt,
        ?string $refusalReason,
        ?string $biometryLink,
        ?ProposalParticipantState $buyer,
        ?ProposalParticipantState $seller,
        ?ProposalParticipantState $store
    ) {
        $this->id = $id;
        $this->number = $number;
        $this->simulationId = $simulationId;
        $this->status = $status;
        $this->statusRaw = $statusRaw;
        $this->statusName = $statusName;
        $this->type = $type;
        $this->typeRaw = $typeRaw;
        $this->createdAt = $createdAt;
        $this->refusalReason = $refusalReason;
        $this->biometryLink = $biometryLink;
        $this->buyer = $buyer;
        $this->seller = $seller;
        $this->store = $store;
    }

    /**
     * Ações que o seu sistema precisa executar agora: só participantes cuja etapa ativa tem o integrador como
     * responsável. Etapa 6 gera `SEND_DOCUMENTS` (documentos pendentes), 7 gera `CORRECT_DOCUMENTS` (documentos
     * inválidos), 8 gera `SEND_VEHICLE_DETAILS` e 5 gera `SEND_DEPOSIT_ACCOUNT`.
     *
     * @return array<int, PendingAction>
     */
    public function getPendingActions(): array
    {
        return PendingActionResolver::resolve($this->buyer, $this->seller, $this->store);
    }
}
