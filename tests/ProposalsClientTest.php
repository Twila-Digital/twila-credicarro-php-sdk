<?php

declare(strict_types=1);

namespace Twila\Credicarro\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Twila\Credicarro\Errors\CredicarroApiException;
use Twila\Credicarro\Errors\CredicarroBusinessRuleException;
use Twila\Credicarro\Errors\CredicarroValidationException;
use Twila\Credicarro\ListRequest;
use Twila\Credicarro\Proposals\ActionOwner;
use Twila\Credicarro\Proposals\BiometryStatus;
use Twila\Credicarro\Proposals\CreateProposalRequest;
use Twila\Credicarro\Proposals\DepositAccount;
use Twila\Credicarro\Proposals\DepositAccountType;
use Twila\Credicarro\Proposals\DocumentFile;
use Twila\Credicarro\Proposals\DocumentStatus;
use Twila\Credicarro\Proposals\DocumentType;
use Twila\Credicarro\Proposals\ProposalDocumentUpload;
use Twila\Credicarro\Proposals\ProposalParticipant;
use Twila\Credicarro\Proposals\ProposalStatus;
use Twila\Credicarro\Proposals\ProposalStep;
use Twila\Credicarro\Proposals\ProposalType;
use Twila\Credicarro\Proposals\StepSituation;
use Twila\Credicarro\Proposals\VehicleDetails;

final class ProposalsClientTest extends TestCase
{
    private const ID = 'bea8d060-f93d-48eb-b6c4-45f64483cdd9';

    private const DETAILS_WIRE = [
        'id_proposta' => 'bea8d060-f93d-48eb-b6c4-45f64483cdd9',
        'numero' => 171020250001,
        'id_simulacao' => '123e4567-e89b-12d3-a456-426614174000',
        'enum_status' => 2,
        'status' => 'APROVADA',
        'tipo' => 'Refinancing',
        'data_criacao' => '2026-10-12T14:05:08.984-03:00',
        'motivo_recusa' => null,
        'link_biometria' => 'https://onboarding.exemplo.com/sessao/abc123',
        'comprador' => [
            'cpf' => '12345678909',
            'enum_biometria' => 3,
            'biometria' => 'APROVADA',
            'status_ativo' => [
                'enum_etapa' => 7,
                'etapa' => 'CORRECAO_DOCUMENTOS',
                'enum_situacao' => 4,
                'situacao' => 'PENDENTE_CORRECAO',
                'enum_responsavel_acao' => 2,
                'responsavel_acao' => 'INTEGRADOR',
            ],
            'etapas_concluidas' => [
                ['enum_etapa' => 1, 'etapa' => 'PROPOSTA_CRIADA', 'data' => '2026-10-12T14:05:08-03:00'],
                ['enum_etapa' => 4, 'etapa' => 'ANALISE_BIOMETRIA', 'data' => '2026-10-12T14:20:00-03:00'],
            ],
            'documentos' => [
                [
                    'enum_tipo' => 1,
                    'tipo' => 'CNH',
                    'enum_status' => 2,
                    'status' => 'INVALIDO',
                    'motivo' => 'Documento ilegível',
                ],
                ['enum_tipo' => 5, 'tipo' => 'COMPROVANTE_RESIDENCIA', 'enum_status' => 1, 'status' => 'VALIDO'],
            ],
        ],
        'loja' => [
            'cnpj' => '11222333000181',
            'status_ativo' => [
                'enum_etapa' => 6,
                'etapa' => 'ENVIO_DOCUMENTOS',
                'enum_situacao' => 1,
                'situacao' => 'AGUARDANDO_ENVIO',
                'enum_responsavel_acao' => 2,
                'responsavel_acao' => 'INTEGRADOR',
            ],
            'etapas_concluidas' => [],
            'documentos' => [
                ['enum_tipo' => 8, 'tipo' => 'LAUDO_ECV', 'enum_status' => 5, 'status' => 'PENDENTE'],
            ],
        ],
    ];

    private const PAGE_WIRE = [
        'tem_proximo' => false,
        'tem_anterior' => true,
        'numero' => 2,
        'tamanho' => 15,
        'total' => 45,
    ];

    public function testCreateReturnsProposalId(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(201, ['id_proposta' => self::ID])]);

        $id = $h->client->proposals->create(new CreateProposalRequest('12345678909', self::ID));

        self::assertSame(self::ID, $id);
        $request = $h->lastApiRequest();
        self::assertSame('/integration/v2/proposal', $request->getUri()->getPath());
        self::assertSame(['cpf' => '12345678909', 'simulacaoId' => self::ID], TestClientFactory::bodyOf($request));
    }

    public function testCreateRejectsMalformedSimulationId(): void
    {
        $h = TestClientFactory::harness([]);

        $this->expectException(CredicarroValidationException::class);
        $h->client->proposals->create(new CreateProposalRequest('12345678909', 'nao-e-uuid'));
    }

    public function testCreateSurfacesBusinessRuleCode(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(422, [
            'tipo' => 'Proposal.SimulationAlreadyExpired',
            'titulo' => 'Regra de negócio não atendida',
        ])]);

        try {
            $h->client->proposals->create(new CreateProposalRequest('12345678909', self::ID));
            self::fail('Esperava CredicarroBusinessRuleException.');
        } catch (CredicarroBusinessRuleException $e) {
            self::assertSame('Proposal.SimulationAlreadyExpired', $e->getErrorCode());
        }
    }

    public function testGetMapsFullProposalSnapshot(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, self::DETAILS_WIRE)]);

        $proposal = $h->client->proposals->get(self::ID);

        self::assertSame('/integration/v2/proposal/' . self::ID, $h->lastApiRequest()->getUri()->getPath());
        self::assertSame(self::ID, $proposal->id);
        self::assertSame(171020250001, $proposal->number);
        self::assertSame('123e4567-e89b-12d3-a456-426614174000', $proposal->simulationId);
        self::assertSame(ProposalStatus::APPROVED, $proposal->status);
        self::assertSame(2, $proposal->statusRaw);
        self::assertSame('APROVADA', $proposal->statusName);
        self::assertSame(ProposalType::REFINANCING, $proposal->type);
        self::assertSame('Refinancing', $proposal->typeRaw);
        self::assertSame('2026-10-12T14:05:08.984-03:00', $proposal->createdAt);
        self::assertNull($proposal->refusalReason);
        self::assertSame('https://onboarding.exemplo.com/sessao/abc123', $proposal->biometryLink);
        self::assertNull($proposal->seller);

        $buyer = $proposal->buyer;
        self::assertNotNull($buyer);
        self::assertSame('12345678909', $buyer->document);
        self::assertSame(BiometryStatus::APPROVED, $buyer->biometry);
        self::assertSame(3, $buyer->biometryRaw);
        self::assertSame('APROVADA', $buyer->biometryName);
        self::assertNotNull($buyer->activeStep);
        self::assertSame(ProposalStep::DOCUMENT_CORRECTION, $buyer->activeStep->step);
        self::assertSame(7, $buyer->activeStep->stepRaw);
        self::assertSame('CORRECAO_DOCUMENTOS', $buyer->activeStep->stepName);
        self::assertSame(StepSituation::PENDING_CORRECTION, $buyer->activeStep->situation);
        self::assertSame(4, $buyer->activeStep->situationRaw);
        self::assertSame('PENDENTE_CORRECAO', $buyer->activeStep->situationName);
        self::assertSame(ActionOwner::INTEGRATOR, $buyer->activeStep->actionOwner);
        self::assertSame(2, $buyer->activeStep->actionOwnerRaw);
        self::assertSame('INTEGRADOR', $buyer->activeStep->actionOwnerName);
        self::assertCount(2, $buyer->completedSteps);
        self::assertSame(ProposalStep::BIOMETRY_ANALYSIS, $buyer->completedSteps[1]->step);
        self::assertSame(4, $buyer->completedSteps[1]->stepRaw);
        self::assertSame('ANALISE_BIOMETRIA', $buyer->completedSteps[1]->stepName);
        self::assertSame('2026-10-12T14:20:00-03:00', $buyer->completedSteps[1]->date);
        self::assertCount(2, $buyer->documents);
        self::assertSame(DocumentType::CNH, $buyer->documents[0]->type);
        self::assertSame(1, $buyer->documents[0]->typeRaw);
        self::assertSame('CNH', $buyer->documents[0]->typeName);
        self::assertSame(DocumentStatus::INVALID, $buyer->documents[0]->status);
        self::assertSame(2, $buyer->documents[0]->statusRaw);
        self::assertSame('INVALIDO', $buyer->documents[0]->statusName);
        self::assertSame('Documento ilegível', $buyer->documents[0]->reason);
        self::assertNull($buyer->documents[1]->reason);

        $store = $proposal->store;
        self::assertNotNull($store);
        self::assertSame('11222333000181', $store->document);
        self::assertNull($store->biometry);
        self::assertSame(DocumentStatus::PENDING, $store->documents[0]->status);
        self::assertSame(DocumentType::ECV_REPORT, $store->documents[0]->type);
    }

    public function testGetKeepsRawValuesForCodesTheSdkDoesNotKnow(): void
    {
        $wire = self::DETAILS_WIRE;
        $wire['enum_status'] = 42;
        $wire['status'] = 'NOVO_STATUS';
        $wire['tipo'] = 'Leasing';
        $wire['comprador']['status_ativo']['enum_etapa'] = 99;
        $wire['comprador']['status_ativo']['etapa'] = 'ETAPA_NOVA';
        $wire['comprador']['status_ativo']['enum_responsavel_acao'] = 9;
        $wire['comprador']['documentos'][0]['enum_tipo'] = 77;
        $wire['comprador']['documentos'][0]['tipo'] = 'NOVO_TIPO';
        $h = TestClientFactory::harness([TestClientFactory::json(200, $wire)]);

        $proposal = $h->client->proposals->get(self::ID);

        self::assertSame(ProposalStatus::UNKNOWN, $proposal->status);
        self::assertSame(42, $proposal->statusRaw);
        self::assertSame('NOVO_STATUS', $proposal->statusName);
        self::assertSame(ProposalType::UNKNOWN, $proposal->type);
        self::assertSame('Leasing', $proposal->typeRaw);
        self::assertNotNull($proposal->buyer);
        self::assertNotNull($proposal->buyer->activeStep);
        self::assertSame(ProposalStep::UNKNOWN, $proposal->buyer->activeStep->step);
        self::assertSame(99, $proposal->buyer->activeStep->stepRaw);
        self::assertSame('ETAPA_NOVA', $proposal->buyer->activeStep->stepName);
        self::assertSame(ActionOwner::UNKNOWN, $proposal->buyer->activeStep->actionOwner);
        self::assertSame(DocumentType::UNKNOWN, $proposal->buyer->documents[0]->type);
        self::assertSame(77, $proposal->buyer->documents[0]->typeRaw);
        self::assertSame('NOVO_TIPO', $proposal->buyer->documents[0]->typeName);
    }

    public function testGetDoesNotReturnWhenProposalIsNotOnV2(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(422, ['tipo' => 'Proposal.Access.NotIntegrationV2'])]);

        $this->expectException(CredicarroBusinessRuleException::class);
        $h->client->proposals->get(self::ID);
    }

    public function testListMapsV1Summaries(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(200, [
            'itens' => [
                [
                    'id_proposta' => self::ID,
                    'numero' => 171020250001,
                    'id_simulacao' => '123e4567-e89b-12d3-a456-426614174000',
                    'tipo' => 'Financing',
                    'status' => 'Approved',
                    'comprador' => ['cpf' => '123.456.789-01'],
                    'vendedor' => ['cpf' => '987.654.321-00'],
                    'data_criacao' => '2025-10-17T19:05:08.984Z',
                ],
                [
                    'id_proposta' => '123e4567-e89b-12d3-a456-426614174000',
                    'numero' => 2,
                    'id_simulacao' => self::ID,
                    'tipo' => 'Refinancing',
                    'status' => 'Whatever',
                    'comprador' => ['cpf' => '123.456.789-01'],
                    'data_criacao' => '2025-10-18T19:05:08.984Z',
                ],
            ],
            'pagina' => self::PAGE_WIRE,
        ])]);

        $page = $h->client->proposals->list(new ListRequest(2, 15));

        self::assertSame('/integration/v1/proposal', $h->lastApiRequest()->getUri()->getPath());
        self::assertSame('pagina_inicial=2&limite=15', $h->lastApiRequest()->getUri()->getQuery());
        self::assertSame(45, $page->totalCount);
        self::assertTrue($page->hasPrevious);
        self::assertFalse($page->hasNext);
        self::assertSame(2, $page->page);
        self::assertSame(15, $page->pageSize);
        self::assertCount(2, $page->items);

        $first = $page->items[0];
        self::assertSame(self::ID, $first->id);
        self::assertSame(171020250001, $first->number);
        self::assertSame(ProposalType::FINANCING, $first->type);
        self::assertSame(ProposalStatus::APPROVED, $first->status);
        self::assertSame('Approved', $first->statusName);
        self::assertSame('123.456.789-01', $first->buyerCpf);
        self::assertSame('987.654.321-00', $first->sellerCpf);
        self::assertSame('2025-10-17T19:05:08.984Z', $first->createdAt);

        $second = $page->items[1];
        self::assertSame(ProposalType::REFINANCING, $second->type);
        self::assertSame(ProposalStatus::UNKNOWN, $second->status);
        self::assertSame('Whatever', $second->statusName);
        self::assertNull($second->sellerCpf);
    }

    public function testSendVehicleDetailsPostsBodyAndAccepts204(): void
    {
        $h = TestClientFactory::harness([new Response(204)]);

        $h->client->proposals->sendVehicleDetails(
            self::ID,
            new VehicleDetails('12345678900', '9BWZZZ377VT004251', 'ABC1D23', 'PRATA')
        );

        $request = $h->lastApiRequest();
        self::assertSame('/integration/v2/proposal/' . self::ID . '/vehicle-detail', $request->getUri()->getPath());
        self::assertSame([
            'renavam' => '12345678900',
            'chassi' => '9BWZZZ377VT004251',
            'placa' => 'ABC1D23',
            'cor' => 'PRATA',
        ], TestClientFactory::bodyOf($request));
    }

    public function testSendVehicleDetailsOutsideTheStageRaisesBusinessRule(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(422, ['tipo' => 'Proposal.InvalidStatus'])]);

        try {
            $h->client->proposals->sendVehicleDetails(self::ID, new VehicleDetails('1', '2', '3', '4'));
            self::fail('Esperava CredicarroBusinessRuleException.');
        } catch (CredicarroBusinessRuleException $e) {
            self::assertSame('Proposal.InvalidStatus', $e->getErrorCode());
        }
    }

    public function testSendDepositAccountPostsBody(): void
    {
        $h = TestClientFactory::harness([new Response(204)]);

        $h->client->proposals->sendDepositAccount(
            self::ID,
            new DepositAccount('12345678909', '001', '1234', '5', '123456', '7', DepositAccountType::SAVINGS)
        );

        $request = $h->lastApiRequest();
        self::assertSame('/integration/v2/proposal/' . self::ID . '/deposit-account', $request->getUri()->getPath());
        self::assertSame([
            'cpfTitular' => '12345678909',
            'codigoBanco' => '001',
            'agencia' => '1234',
            'digitoAgencia' => '5',
            'conta' => '123456',
            'digitoConta' => '7',
            'tipoConta' => 'POUPANCA',
        ], TestClientFactory::bodyOf($request));
    }

    public function testUploadDocumentPostsBase64BodyWithKeyHeader(): void
    {
        $h = TestClientFactory::harness([new Response(204)]);

        $h->client->proposals->uploadDocument(
            self::ID,
            new ProposalDocumentUpload(
                ProposalParticipant::SELLER,
                DocumentType::CNH,
                DocumentFile::fromBytes('%PDF-1.7 conteudo', 'cnh-vendedor.PDF')
            ),
            'envio-0001'
        );

        $request = $h->lastApiRequest();
        self::assertSame('/integration/v2/proposal/' . self::ID . '/document', $request->getUri()->getPath());
        self::assertSame('envio-0001', $request->getHeaderLine('Idempotency-Key'));
        self::assertSame([
            'participante' => 'VENDEDOR',
            'tipoDocumento' => 1,
            'nomeArquivo' => 'cnh-vendedor.PDF',
            'conteudoBase64' => base64_encode('%PDF-1.7 conteudo'),
        ], TestClientFactory::bodyOf($request));
    }

    public function testUploadDocumentRejectsUnknownParticipantLocally(): void
    {
        $h = TestClientFactory::harness([]);

        try {
            $h->client->proposals->uploadDocument(
                self::ID,
                new ProposalDocumentUpload('FIADOR', DocumentType::CNH, DocumentFile::fromBytes('x', 'a.pdf'))
            );
            self::fail('Esperava CredicarroValidationException.');
        } catch (CredicarroValidationException $e) {
            self::assertSame('Validation.Field.InvalidOption', $e->getErrorCode());
            self::assertSame('participante', $e->getField());
        }
    }

    public function testUploadDocumentSurfacesKeyConflict(): void
    {
        $h = TestClientFactory::harness([TestClientFactory::json(409, ['tipo' => 'Idempotency.KeyConflict'])]);

        try {
            $h->client->proposals->uploadDocument(
                self::ID,
                new ProposalDocumentUpload(
                    ProposalParticipant::BUYER,
                    DocumentType::CNH,
                    DocumentFile::fromBytes('x', 'a.pdf')
                ),
                'chave-reutilizada'
            );
            self::fail('Esperava CredicarroApiException.');
        } catch (CredicarroApiException $e) {
            self::assertSame(409, $e->getStatusCode());
            self::assertSame('Idempotency.KeyConflict', $e->getErrorCode());
            self::assertCount(1, $h->apiRequests());
        }
    }

    public function testProposalIdIsValidatedAsUuid(): void
    {
        $h = TestClientFactory::harness([]);

        $this->expectException(CredicarroValidationException::class);
        $h->client->proposals->get('123/../../x');
    }
}
