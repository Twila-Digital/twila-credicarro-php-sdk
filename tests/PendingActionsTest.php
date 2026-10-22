<?php

declare(strict_types=1);

namespace Twila\Credicarro\Tests;

use PHPUnit\Framework\TestCase;
use Twila\Credicarro\Proposals\PendingActionKind;
use Twila\Credicarro\Proposals\ProposalDetails;
use Twila\Credicarro\Proposals\ProposalParticipant;
use Twila\Credicarro\Proposals\ProposalStep;

final class PendingActionsTest extends TestCase
{
    /**
     * @param array<string, mixed> $participants comprador / vendedor / loja
     */
    private function fetch(array $participants): ProposalDetails
    {
        $wire = array_merge([
            'id_proposta' => TestClientFactory::UUID,
            'numero' => 1,
            'id_simulacao' => TestClientFactory::UUID,
            'enum_status' => 2,
            'status' => 'APROVADA',
            'tipo' => 'Financing',
            'data_criacao' => '2026-10-12T14:05:08-03:00',
        ], $participants);

        $h = TestClientFactory::harness([TestClientFactory::json(200, $wire)]);

        return $h->client->proposals->get(TestClientFactory::UUID);
    }

    /**
     * @param array<int, array<string, mixed>> $documents
     * @return array<string, mixed>
     */
    private static function participant(int $step, string $stepName, int $owner, array $documents = []): array
    {
        return [
            'cpf' => '12345678909',
            'status_ativo' => [
                'enum_etapa' => $step,
                'etapa' => $stepName,
                'enum_situacao' => 1,
                'situacao' => 'AGUARDANDO_ENVIO',
                'enum_responsavel_acao' => $owner,
                'responsavel_acao' => 'X',
            ],
            'etapas_concluidas' => [],
            'documentos' => $documents,
        ];
    }

    public function testDocumentSubmissionListsOnlyPendingDocuments(): void
    {
        $proposal = $this->fetch(['comprador' => self::participant(6, 'ENVIO_DOCUMENTOS', 2, [
            ['enum_tipo' => 5, 'tipo' => 'COMPROVANTE_RESIDENCIA', 'enum_status' => 5, 'status' => 'PENDENTE'],
            ['enum_tipo' => 1, 'tipo' => 'CNH', 'enum_status' => 1, 'status' => 'VALIDO'],
            ['enum_tipo' => 4, 'tipo' => 'COMPROVANTE_RENDA', 'enum_status' => 5, 'status' => 'PENDENTE'],
        ])]);

        $actions = $proposal->getPendingActions();

        self::assertCount(1, $actions);
        self::assertSame(ProposalParticipant::BUYER, $actions[0]->participant);
        self::assertSame(ProposalStep::DOCUMENT_SUBMISSION, $actions[0]->step);
        self::assertSame(PendingActionKind::SEND_DOCUMENTS, $actions[0]->kind);
        self::assertSame([5, 4], $actions[0]->documentTypes);
    }

    public function testDocumentCorrectionListsOnlyInvalidDocuments(): void
    {
        $proposal = $this->fetch(['comprador' => self::participant(7, 'CORRECAO_DOCUMENTOS', 2, [
            ['enum_tipo' => 1, 'tipo' => 'CNH', 'enum_status' => 2, 'status' => 'INVALIDO', 'motivo' => 'Ilegível'],
            ['enum_tipo' => 5, 'tipo' => 'COMPROVANTE_RESIDENCIA', 'enum_status' => 1, 'status' => 'VALIDO'],
            ['enum_tipo' => 4, 'tipo' => 'COMPROVANTE_RENDA', 'enum_status' => 5, 'status' => 'PENDENTE'],
        ])]);

        $actions = $proposal->getPendingActions();

        self::assertCount(1, $actions);
        self::assertSame(PendingActionKind::CORRECT_DOCUMENTS, $actions[0]->kind);
        self::assertSame([1], $actions[0]->documentTypes);
    }

    public function testVehicleDetailsStageRequestsVehicleData(): void
    {
        $proposal = $this->fetch(['loja' => self::participant(8, 'DADOS_VEICULO', 2)]);

        $actions = $proposal->getPendingActions();

        self::assertCount(1, $actions);
        self::assertSame(ProposalParticipant::STORE, $actions[0]->participant);
        self::assertSame(PendingActionKind::SEND_VEHICLE_DETAILS, $actions[0]->kind);
        self::assertSame([], $actions[0]->documentTypes);
    }

    public function testDepositAccountStageRequestsDepositAccount(): void
    {
        $proposal = $this->fetch(['comprador' => self::participant(5, 'CONTA_RECEBIMENTO', 2)]);

        $actions = $proposal->getPendingActions();

        self::assertCount(1, $actions);
        self::assertSame(PendingActionKind::SEND_DEPOSIT_ACCOUNT, $actions[0]->kind);
        self::assertSame(ProposalStep::DEPOSIT_ACCOUNT, $actions[0]->step);
    }

    public function testActionsOfOtherOwnersAreIgnored(): void
    {
        $proposal = $this->fetch([
            'comprador' => self::participant(6, 'ENVIO_DOCUMENTOS', 1),
            'loja' => self::participant(8, 'DADOS_VEICULO', 3),
        ]);

        self::assertSame([], $proposal->getPendingActions());
    }

    public function testStepsWithoutIntegratorActionProduceNothing(): void
    {
        $proposal = $this->fetch([
            'comprador' => self::participant(4, 'ANALISE_BIOMETRIA', 2),
            'loja' => self::participant(10, 'ASSINATURA', 2),
        ]);

        self::assertSame([], $proposal->getPendingActions());
    }

    public function testBuyerSellerAndStoreAreReportedInOrder(): void
    {
        $proposal = $this->fetch([
            'loja' => self::participant(8, 'DADOS_VEICULO', 2),
            'vendedor' => self::participant(6, 'ENVIO_DOCUMENTOS', 2, [
                ['enum_tipo' => 2, 'tipo' => 'CPF', 'enum_status' => 5, 'status' => 'PENDENTE'],
            ]),
            'comprador' => self::participant(6, 'ENVIO_DOCUMENTOS', 2, [
                ['enum_tipo' => 1, 'tipo' => 'CNH', 'enum_status' => 5, 'status' => 'PENDENTE'],
            ]),
        ]);

        $actions = $proposal->getPendingActions();

        self::assertCount(3, $actions);
        self::assertSame(
            [ProposalParticipant::BUYER, ProposalParticipant::SELLER, ProposalParticipant::STORE],
            [$actions[0]->participant, $actions[1]->participant, $actions[2]->participant]
        );
    }

    public function testNoParticipantsMeansNoActions(): void
    {
        self::assertSame([], $this->fetch([])->getPendingActions());
    }
}
