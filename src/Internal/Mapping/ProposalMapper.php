<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Mapping;

use Twila\Credicarro\Proposals\ActiveStep;
use Twila\Credicarro\Proposals\ActionOwner;
use Twila\Credicarro\Proposals\BiometryStatus;
use Twila\Credicarro\Proposals\CompletedStep;
use Twila\Credicarro\Proposals\CreateProposalRequest;
use Twila\Credicarro\Proposals\DepositAccount;
use Twila\Credicarro\Proposals\DocumentStatus;
use Twila\Credicarro\Proposals\DocumentType;
use Twila\Credicarro\Proposals\ProposalDetails;
use Twila\Credicarro\Proposals\ProposalDocument;
use Twila\Credicarro\Proposals\ProposalDocumentUpload;
use Twila\Credicarro\Proposals\ProposalParticipantState;
use Twila\Credicarro\Proposals\ProposalStatus;
use Twila\Credicarro\Proposals\ProposalStep;
use Twila\Credicarro\Proposals\ProposalSummary;
use Twila\Credicarro\Proposals\ProposalType;
use Twila\Credicarro\Proposals\StepSituation;
use Twila\Credicarro\Proposals\VehicleDetails;

/**
 * @internal
 */
final class ProposalMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function createRequestToWire(CreateProposalRequest $request): array
    {
        return [
            'cpf' => $request->cpf,
            'simulacaoId' => $request->simulationId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function vehicleDetailsToWire(VehicleDetails $details): array
    {
        return [
            'renavam' => $details->renavam,
            'chassi' => $details->chassis,
            'placa' => $details->licensePlate,
            'cor' => $details->color,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function depositAccountToWire(DepositAccount $account): array
    {
        return [
            'cpfTitular' => $account->holderCpf,
            'codigoBanco' => $account->bankCode,
            'agencia' => $account->branch,
            'digitoAgencia' => $account->branchDigit,
            'conta' => $account->accountNumber,
            'digitoConta' => $account->accountDigit,
            'tipoConta' => $account->accountType,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function documentUploadToWire(ProposalDocumentUpload $upload): array
    {
        return [
            'participante' => $upload->participant,
            'tipoDocumento' => $upload->documentType,
            'nomeArquivo' => $upload->file->fileName,
            'conteudoBase64' => $upload->file->base64Content,
        ];
    }

    /**
     * `GET v2/proposal/{id}`.
     *
     * @param array<mixed> $wire
     */
    public static function detailsFromWire(array $wire): ProposalDetails
    {
        $statusRaw = Wire::nullableInt($wire, 'enum_status');
        $statusName = Wire::nullableString($wire, 'status');
        $typeRaw = Wire::nullableString($wire, 'tipo');

        return new ProposalDetails(
            Wire::string($wire, 'id_proposta'),
            Wire::int($wire, 'numero'),
            Wire::string($wire, 'id_simulacao'),
            $statusRaw !== null ? ProposalStatus::fromWireValue($statusRaw) : ProposalStatus::fromWireName($statusName),
            $statusRaw,
            $statusName,
            ProposalType::fromWireName($typeRaw),
            $typeRaw,
            Wire::string($wire, 'data_criacao'),
            Wire::nullableString($wire, 'motivo_recusa'),
            Wire::nullableString($wire, 'link_biometria'),
            self::participantFromWire(Wire::object($wire, 'comprador'), 'cpf'),
            self::participantFromWire(Wire::object($wire, 'vendedor'), 'cpf'),
            self::participantFromWire(Wire::object($wire, 'loja'), 'cnpj')
        );
    }

    /**
     * `GET v1/proposal` e `GET v1/proposal/{id}`.
     *
     * @param array<mixed> $wire
     */
    public static function summaryFromWire(array $wire): ProposalSummary
    {
        $statusName = Wire::nullableString($wire, 'status');
        $buyer = Wire::object($wire, 'comprador');
        $seller = Wire::object($wire, 'vendedor');

        return new ProposalSummary(
            Wire::string($wire, 'id_proposta'),
            Wire::int($wire, 'numero'),
            Wire::string($wire, 'id_simulacao'),
            ProposalType::fromWireName(Wire::nullableString($wire, 'tipo')),
            ProposalStatus::fromWireName($statusName),
            $statusName,
            $buyer !== null ? Wire::nullableString($buyer, 'cpf') : null,
            $seller !== null ? Wire::nullableString($seller, 'cpf') : null,
            Wire::string($wire, 'data_criacao')
        );
    }

    /**
     * Participante de `ProposalV2Response` e do payload do webhook (comprador, vendedor e loja).
     *
     * @param array<mixed>|null $wire
     * @param string $documentKey `cpf` (comprador/vendedor) ou `cnpj` (loja)
     */
    public static function participantFromWire(?array $wire, string $documentKey): ?ProposalParticipantState
    {
        if ($wire === null) {
            return null;
        }

        $biometryRaw = Wire::nullableInt($wire, 'enum_biometria');
        $biometryName = Wire::nullableString($wire, 'biometria');
        $biometry = null;
        if ($biometryRaw !== null) {
            $biometry = BiometryStatus::fromWireValue($biometryRaw);
        } elseif ($biometryName !== null) {
            $biometry = BiometryStatus::fromWireName($biometryName);
        }

        $completed = [];
        foreach (Wire::objects($wire, 'etapas_concluidas') as $item) {
            $stepRaw = Wire::nullableInt($item, 'enum_etapa');
            $stepName = Wire::nullableString($item, 'etapa');
            $completed[] = new CompletedStep(
                $stepRaw !== null ? ProposalStep::fromWireValue($stepRaw) : ProposalStep::fromWireName($stepName),
                $stepRaw,
                $stepName,
                Wire::nullableString($item, 'data')
            );
        }

        $documents = [];
        foreach (Wire::objects($wire, 'documentos') as $item) {
            $typeRaw = Wire::nullableInt($item, 'enum_tipo');
            $typeName = Wire::nullableString($item, 'tipo');
            $statusRaw = Wire::nullableInt($item, 'enum_status');
            $statusName = Wire::nullableString($item, 'status');
            $documents[] = new ProposalDocument(
                $typeRaw !== null ? DocumentType::fromWireValue($typeRaw) : DocumentType::fromWireName($typeName),
                $typeRaw,
                $typeName,
                $statusRaw !== null
                    ? DocumentStatus::fromWireValue($statusRaw)
                    : DocumentStatus::fromWireName($statusName),
                $statusRaw,
                $statusName,
                Wire::nullableString($item, 'motivo')
            );
        }

        return new ProposalParticipantState(
            Wire::nullableString($wire, $documentKey),
            $biometry,
            $biometryRaw,
            $biometryName,
            self::activeStepFromWire(Wire::object($wire, 'status_ativo')),
            $completed,
            $documents
        );
    }

    /**
     * @param array<mixed>|null $wire
     */
    private static function activeStepFromWire(?array $wire): ?ActiveStep
    {
        if ($wire === null) {
            return null;
        }

        $stepRaw = Wire::nullableInt($wire, 'enum_etapa');
        $stepName = Wire::nullableString($wire, 'etapa');
        $situationRaw = Wire::nullableInt($wire, 'enum_situacao');
        $situationName = Wire::nullableString($wire, 'situacao');
        $ownerRaw = Wire::nullableInt($wire, 'enum_responsavel_acao');
        $ownerName = Wire::nullableString($wire, 'responsavel_acao');

        return new ActiveStep(
            $stepRaw !== null ? ProposalStep::fromWireValue($stepRaw) : ProposalStep::fromWireName($stepName),
            $stepRaw,
            $stepName,
            $situationRaw !== null
                ? StepSituation::fromWireValue($situationRaw)
                : StepSituation::fromWireName($situationName),
            $situationRaw,
            $situationName,
            $ownerRaw !== null ? ActionOwner::fromWireValue($ownerRaw) : ActionOwner::fromWireName($ownerName),
            $ownerRaw,
            $ownerName
        );
    }
}
