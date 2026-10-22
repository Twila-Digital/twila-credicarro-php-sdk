<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

use Twila\Credicarro\Internal\Masking\MasksSensitiveData;

/**
 * Situação de um participante (comprador, vendedor ou loja) na proposta ou no webhook.
 */
final class ProposalParticipantState
{
    use MasksSensitiveData;

    /** @var string|null CPF (comprador/vendedor) ou CNPJ (loja) */
    public $document;

    /** @var int|null um dos BiometryStatus::* (ausente na loja) */
    public $biometry;

    /** @var int|null `enum_biometria` */
    public $biometryRaw;

    /** @var string|null `biometria` */
    public $biometryName;

    /** @var ActiveStep|null `status_ativo` */
    public $activeStep;

    /** @var array<int, CompletedStep> `etapas_concluidas` */
    public $completedSteps;

    /** @var array<int, ProposalDocument> `documentos` */
    public $documents;

    /**
     * @param array<int, CompletedStep> $completedSteps
     * @param array<int, ProposalDocument> $documents
     */
    public function __construct(
        ?string $document,
        ?int $biometry,
        ?int $biometryRaw,
        ?string $biometryName,
        ?ActiveStep $activeStep,
        array $completedSteps,
        array $documents
    ) {
        $this->document = $document;
        $this->biometry = $biometry;
        $this->biometryRaw = $biometryRaw;
        $this->biometryName = $biometryName;
        $this->activeStep = $activeStep;
        $this->completedSteps = $completedSteps;
        $this->documents = $documents;
    }

    /**
     * @return array<string, string>
     */
    protected function sensitiveFields(): array
    {
        return [
            'document' => 'cpf',
        ];
    }
}
