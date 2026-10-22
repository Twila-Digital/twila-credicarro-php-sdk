<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

use Twila\Credicarro\Internal\Masking\MasksSensitiveData;

/**
 * Proposta resumida da v1 (`GET v1/proposal` e `GET v1/proposal/{id}`). A v1 só informa o nome do status, em inglês.
 */
final class ProposalSummary
{
    use MasksSensitiveData;

    /** @var string `id_proposta` */
    public $id;

    /** @var int `numero` */
    public $number;

    /** @var string `id_simulacao` */
    public $simulationId;

    /** @var int um dos ProposalType::* */
    public $type;

    /** @var int um dos ProposalStatus::* */
    public $status;

    /** @var string|null `status` (por exemplo `Approved`) */
    public $statusName;

    /** @var string|null `comprador.cpf` */
    public $buyerCpf;

    /** @var string|null `vendedor.cpf` — omitido quando a proposta não tem vendedor */
    public $sellerCpf;

    /** @var string `data_criacao` (ISO-8601) */
    public $createdAt;

    public function __construct(
        string $id,
        int $number,
        string $simulationId,
        int $type,
        int $status,
        ?string $statusName,
        ?string $buyerCpf,
        ?string $sellerCpf,
        string $createdAt
    ) {
        $this->id = $id;
        $this->number = $number;
        $this->simulationId = $simulationId;
        $this->type = $type;
        $this->status = $status;
        $this->statusName = $statusName;
        $this->buyerCpf = $buyerCpf;
        $this->sellerCpf = $sellerCpf;
        $this->createdAt = $createdAt;
    }

    /**
     * @return array<string, string>
     */
    protected function sensitiveFields(): array
    {
        return [
            'buyerCpf' => 'cpf',
            'sellerCpf' => 'cpf',
        ];
    }
}
