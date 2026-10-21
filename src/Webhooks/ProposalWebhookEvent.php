<?php

declare(strict_types=1);

namespace Twila\Credicarro\Webhooks;

use Twila\Credicarro\Proposals\ProposalParticipantState;

/**
 * Notificação do webhook de proposta. O payload v1 traz só `proposalId` e o status; o payload v2 (`isDetailed`)
 * traz também o motivo do evento e o retrato do comprador, da loja e do vendedor.
 */
final class ProposalWebhookEvent
{
    /** @var string `id_proposta` */
    public $proposalId;

    /** @var int um dos \Twila\Credicarro\Proposals\ProposalStatus::* */
    public $status;

    /** @var int|null `enum_status` */
    public $statusRaw;

    /** @var string|null `status` (português no payload v2, inglês no v1) */
    public $statusName;

    /** @var int|null um dos ProposalEventReason::* (só no payload v2) */
    public $eventReason;

    /** @var string|null `motivo_evento` (valor original) */
    public $eventReasonRaw;

    /** @var string|null `motivo_recusa` */
    public $refusalReason;

    /** @var string|null `data_evento` (UTC, ISO-8601) */
    public $eventDate;

    /** @var ProposalParticipantState|null `comprador` */
    public $buyer;

    /** @var ProposalParticipantState|null `vendedor` (hoje sempre null) */
    public $seller;

    /** @var ProposalParticipantState|null `loja` */
    public $store;

    /** @var bool true no payload v2 (retrato completo) */
    public $isDetailed;

    public function __construct(
        string $proposalId,
        int $status,
        ?int $statusRaw,
        ?string $statusName,
        ?int $eventReason,
        ?string $eventReasonRaw,
        ?string $refusalReason,
        ?string $eventDate,
        ?ProposalParticipantState $buyer,
        ?ProposalParticipantState $seller,
        ?ProposalParticipantState $store,
        bool $isDetailed
    ) {
        $this->proposalId = $proposalId;
        $this->status = $status;
        $this->statusRaw = $statusRaw;
        $this->statusName = $statusName;
        $this->eventReason = $eventReason;
        $this->eventReasonRaw = $eventReasonRaw;
        $this->refusalReason = $refusalReason;
        $this->eventDate = $eventDate;
        $this->buyer = $buyer;
        $this->seller = $seller;
        $this->store = $store;
        $this->isDetailed = $isDetailed;
    }
}
