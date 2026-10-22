<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

/**
 * Documento de um participante na consulta da proposta (`documentos[]`).
 */
final class ProposalDocument
{
    /** @var int um dos DocumentType::* */
    public $type;

    /** @var int|null `enum_tipo` */
    public $typeRaw;

    /** @var string|null `tipo` */
    public $typeName;

    /** @var int um dos DocumentStatus::* */
    public $status;

    /** @var int|null `enum_status` */
    public $statusRaw;

    /** @var string|null `status` */
    public $statusName;

    /** @var string|null `motivo` — motivo da recusa, quando houver */
    public $reason;

    public function __construct(
        int $type,
        ?int $typeRaw,
        ?string $typeName,
        int $status,
        ?int $statusRaw,
        ?string $statusName,
        ?string $reason = null
    ) {
        $this->type = $type;
        $this->typeRaw = $typeRaw;
        $this->typeName = $typeName;
        $this->status = $status;
        $this->statusRaw = $statusRaw;
        $this->statusName = $statusName;
        $this->reason = $reason;
    }
}
