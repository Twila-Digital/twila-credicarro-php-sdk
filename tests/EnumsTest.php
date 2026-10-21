<?php

declare(strict_types=1);

namespace Twila\Credicarro\Tests;

use PHPUnit\Framework\TestCase;
use Twila\Credicarro\Customers\OccupationType;
use Twila\Credicarro\Proposals\ActionOwner;
use Twila\Credicarro\Proposals\BiometryStatus;
use Twila\Credicarro\Proposals\DocumentStatus;
use Twila\Credicarro\Proposals\DocumentType;
use Twila\Credicarro\Proposals\ProposalStatus;
use Twila\Credicarro\Proposals\ProposalStep;
use Twila\Credicarro\Proposals\ProposalType;
use Twila\Credicarro\Proposals\StepSituation;
use Twila\Credicarro\Simulations\SimulationType;
use Twila\Credicarro\Webhooks\ProposalEventReason;
use Twila\Credicarro\Webhooks\WebhookAuthenticationType;
use Twila\Credicarro\Webhooks\WebhookStatus;
use Twila\Credicarro\Webhooks\WebhookType;

final class EnumsTest extends TestCase
{
    public function testProposalStatusCodesAndBothLanguages(): void
    {
        $expected = [
            1 => ['EM_ANALISE', 'Analysing'],
            2 => ['APROVADA', 'Approved'],
            3 => ['RECUSADA', 'Refused'],
            4 => ['CANCELADA', 'Canceled'],
            5 => ['ANALISE_MANUAL', 'ManualAnalysis'],
            6 => ['FINALIZADA', 'Completed'],
            7 => ['EM_NEGOCIACAO', 'InNegotiation'],
            8 => ['EXPIRADA', 'Expired'],
            9 => ['PENDENTE', 'Pending'],
        ];

        foreach ($expected as $code => $names) {
            self::assertSame($code, ProposalStatus::fromWireValue($code));
            self::assertSame($code, ProposalStatus::fromWireName($names[0]));
            self::assertSame($code, ProposalStatus::fromWireName($names[1]));
            self::assertSame($code, ProposalStatus::fromWireName(strtolower($names[1])));
            self::assertSame($names[0], ProposalStatus::wireName($code));
        }

        self::assertSame(ProposalStatus::UNKNOWN, ProposalStatus::fromWireValue(0));
        self::assertSame(ProposalStatus::UNKNOWN, ProposalStatus::fromWireValue(10));
        self::assertSame(ProposalStatus::UNKNOWN, ProposalStatus::fromWireName('NOVO'));
        self::assertSame(ProposalStatus::UNKNOWN, ProposalStatus::fromWireName(null));
        self::assertSame(-1, ProposalStatus::UNKNOWN);
    }

    public function testProposalStepCodes(): void
    {
        $names = [
            0 => 'INDEFINIDO', 1 => 'PROPOSTA_CRIADA', 2 => 'PROPOSTA_APROVADA', 3 => 'NOTIFICACAO_VENDEDOR',
            4 => 'ANALISE_BIOMETRIA', 5 => 'CONTA_RECEBIMENTO', 6 => 'ENVIO_DOCUMENTOS', 7 => 'CORRECAO_DOCUMENTOS',
            8 => 'DADOS_VEICULO', 9 => 'VISTORIA', 10 => 'ASSINATURA', 11 => 'PAGAMENTO', 12 => 'PROPOSTA_FINALIZADA',
        ];

        foreach ($names as $code => $name) {
            self::assertSame($code, ProposalStep::fromWireValue($code));
            self::assertSame($code, ProposalStep::fromWireName($name));
        }

        self::assertSame(ProposalStep::UNDEFINED, 0);
        self::assertSame(ProposalStep::UNKNOWN, ProposalStep::fromWireValue(13));
        self::assertSame(ProposalStep::UNKNOWN, ProposalStep::fromWireValue(-5));
    }

    public function testSituationOwnerBiometryAndDocumentStatusCodes(): void
    {
        self::assertSame(1, StepSituation::AWAITING_SUBMISSION);
        self::assertSame(7, StepSituation::CANCELED);
        self::assertSame(StepSituation::PENDING_CORRECTION, StepSituation::fromWireName('PENDENTE_CORRECAO'));
        self::assertSame(StepSituation::UNKNOWN, StepSituation::fromWireValue(8));

        self::assertSame(0, ActionOwner::NONE);
        self::assertSame(2, ActionOwner::INTEGRATOR);
        self::assertSame(ActionOwner::CREDICARRO, ActionOwner::fromWireName('CREDICARRO'));
        self::assertSame(ActionOwner::UNKNOWN, ActionOwner::fromWireValue(4));

        self::assertSame(0, BiometryStatus::NOT_STARTED);
        self::assertSame(BiometryStatus::REJECTED, BiometryStatus::fromWireName('REPROVADA'));
        self::assertSame(BiometryStatus::UNKNOWN, BiometryStatus::fromWireValue(5));

        self::assertSame(0, DocumentStatus::RECEIVED);
        self::assertSame(5, DocumentStatus::PENDING);
        self::assertSame(DocumentStatus::DELETED, DocumentStatus::fromWireName('EXCLUIDO'));
        self::assertSame(DocumentStatus::UNKNOWN, DocumentStatus::fromWireValue(6));
    }

    public function testDocumentTypeCodes(): void
    {
        $expected = [
            1 => 'CNH', 2 => 'CPF', 3 => 'RG', 4 => 'COMPROVANTE_RENDA', 5 => 'COMPROVANTE_RESIDENCIA',
            6 => 'DOCUMENTO_VEICULO', 7 => 'CNH_DIGITAL', 8 => 'LAUDO_ECV', 9 => 'LAUDO_VISTORIA',
            11 => 'CARTAO_CNPJ', 57 => 'ATPV',
        ];

        foreach ($expected as $code => $name) {
            self::assertSame($code, DocumentType::fromWireValue($code));
            self::assertSame($code, DocumentType::fromWireName($name));
        }

        self::assertSame(DocumentType::UNKNOWN, DocumentType::fromWireValue(10));
        self::assertSame(DocumentType::UNKNOWN, DocumentType::fromWireValue(56));
        self::assertSame(57, DocumentType::ATPV);
    }

    public function testTextualEnumsFallBackToUnknown(): void
    {
        self::assertSame(ProposalType::FINANCING, ProposalType::fromWireName('Financing'));
        self::assertSame(ProposalType::REFINANCING, ProposalType::fromWireName('refinancing'));
        self::assertSame(ProposalType::UNKNOWN, ProposalType::fromWireName('Leasing'));
        self::assertSame(SimulationType::AMOUNT_SIMULATION, SimulationType::fromWireName('AmountSimulation'));
        self::assertSame(SimulationType::UNKNOWN, SimulationType::fromWireName(''));
        self::assertSame(ProposalEventReason::STATUS_CHANGED, ProposalEventReason::fromWireName('STATUS_ALTERADO'));
        self::assertSame(ProposalEventReason::STEPS_CHANGED, ProposalEventReason::fromWireName('ETAPAS_ALTERADAS'));
        self::assertSame(
            ProposalEventReason::DOCUMENTS_CHANGED,
            ProposalEventReason::fromWireName('DOCUMENTOS_ALTERADOS')
        );
        self::assertSame(ProposalEventReason::UNKNOWN, ProposalEventReason::fromWireName('OUTRO'));
    }

    public function testWebhookAndOccupationEnums(): void
    {
        self::assertSame([1, 2, 3], [WebhookType::CUSTOMER, WebhookType::SIMULATION, WebhookType::PROPOSAL]);
        self::assertSame(WebhookType::SIMULATION, WebhookType::fromWireName('Simulation'));
        self::assertSame(WebhookType::UNKNOWN, WebhookType::fromWireValue(4));

        self::assertSame([1, 2, 3], [
            WebhookAuthenticationType::NONE,
            WebhookAuthenticationType::BASIC,
            WebhookAuthenticationType::JWT,
        ]);
        self::assertSame(WebhookAuthenticationType::JWT, WebhookAuthenticationType::fromWireName('Jwt'));
        self::assertSame(WebhookAuthenticationType::UNKNOWN, WebhookAuthenticationType::fromWireValue(0));

        self::assertSame(1, WebhookStatus::ACTIVE);
        self::assertSame(WebhookStatus::INACTIVE, WebhookStatus::fromWireName('Inactive'));
        self::assertSame(WebhookStatus::UNKNOWN, WebhookStatus::fromWireName('Paused'));

        self::assertSame(
            [1, 2, 3, 4, 6, 7],
            [
                OccupationType::RETIRED,
                OccupationType::PENSIONER,
                OccupationType::EMPLOYEE,
                OccupationType::SELF_EMPLOYED,
                OccupationType::FREELANCER,
                OccupationType::BUSINESS_OWNER,
            ]
        );
        self::assertSame(OccupationType::UNKNOWN, OccupationType::fromWireValue(5));
    }
}
