<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

use Twila\Credicarro\Internal\Http\ApiRequestExecutor;
use Twila\Credicarro\Internal\Http\ResponseReader;
use Twila\Credicarro\Internal\Http\UuidGenerator;
use Twila\Credicarro\Internal\Mapping\PagedMapper;
use Twila\Credicarro\Internal\Mapping\ProposalMapper;
use Twila\Credicarro\Internal\Validation;
use Twila\Credicarro\ListRequest;
use Twila\Credicarro\PagedResult;

/**
 * Propostas (v2 para criar, consultar e enviar dados; v1 para a listagem paginada).
 */
final class ProposalsClient
{
    /** @var ApiRequestExecutor */
    private $executor;

    /** @var int */
    private $documentUploadAttemptTimeoutMs;

    public function __construct(ApiRequestExecutor $executor, int $documentUploadAttemptTimeoutMs)
    {
        $this->executor = $executor;
        $this->documentUploadAttemptTimeoutMs = $documentUploadAttemptTimeoutMs;
    }

    /**
     * Cria a proposta a partir de uma simulação feita com `cnpjLoja` (`POST v2/proposal`). O cadastro do cliente
     * precisa estar completo. Recebe Idempotency-Key automática. Devolve o `id_proposta`.
     */
    public function create(CreateProposalRequest $request): string
    {
        Validation::requireUuid($request->simulationId, 'simulacaoId');

        $response = $this->executor->post('v2/proposal', ProposalMapper::createRequestToWire($request));
        ApiRequestExecutor::ensureSuccess($response);

        return ResponseReader::requiredString($response, 'id_proposta');
    }

    /**
     * Retrato atual da proposta (`GET v2/proposal/{id}`). Use `getPendingActions()` para saber o que enviar.
     */
    public function get(string $proposalId): ProposalDetails
    {
        $id = Validation::requireUuid($proposalId, 'propostaId');

        $response = $this->executor->get('v2/proposal/' . rawurlencode($id));
        ApiRequestExecutor::ensureSuccess($response);

        return ProposalMapper::detailsFromWire(ResponseReader::object($response));
    }

    /**
     * Lista as propostas de forma paginada (`GET v1/proposal`). Os itens são `ProposalSummary`.
     */
    public function list(?ListRequest $request = null): PagedResult
    {
        $response = $this->executor->get(PagedMapper::path('v1/proposal', $request));
        ApiRequestExecutor::ensureSuccess($response);

        return PagedMapper::fromWire($response->body, [ProposalMapper::class, 'summaryFromWire']);
    }

    /**
     * Inclui ou atualiza os dados do veículo (`POST v2/proposal/{id}/vehicle-detail`, 204). Aceito só na etapa
     * `DADOS_VEICULO`. Reenviar substitui os dados, então a chamada é segura para repetição.
     */
    public function sendVehicleDetails(string $proposalId, VehicleDetails $details): void
    {
        $id = Validation::requireUuid($proposalId, 'propostaId');

        $response = $this->executor->post(
            'v2/proposal/' . rawurlencode($id) . '/vehicle-detail',
            ProposalMapper::vehicleDetailsToWire($details)
        );
        ApiRequestExecutor::ensureSuccess($response);
    }

    /**
     * Inclui ou substitui a conta de recebimento (`POST v2/proposal/{id}/deposit-account`, 204). Só para
     * refinanciamento e só na etapa `CONTA_RECEBIMENTO`. Reenviar substitui a conta, então é seguro repetir.
     */
    public function sendDepositAccount(string $proposalId, DepositAccount $account): void
    {
        $id = Validation::requireUuid($proposalId, 'propostaId');

        $response = $this->executor->post(
            'v2/proposal/' . rawurlencode($id) . '/deposit-account',
            ProposalMapper::depositAccountToWire($account)
        );
        ApiRequestExecutor::ensureSuccess($response);
    }

    /**
     * Envia um documento (`POST v2/proposal/{id}/document`, 204), um arquivo por chamada.
     *
     * O header Idempotency-Key é obrigatório na API. Recomenda-se gravar uma chave por envio junto ao registro do
     * documento e reutilizá-la nas retentativas; use uma chave NOVA ao reenviar na correção. Sem `$idempotencyKey`,
     * o SDK gera uma e a reutiliza em todas as tentativas desta chamada.
     */
    public function uploadDocument(
        string $proposalId,
        ProposalDocumentUpload $upload,
        ?string $idempotencyKey = null
    ): void {
        $id = Validation::requireUuid($proposalId, 'propostaId');

        if (!in_array($upload->participant, ProposalParticipant::values(), true)) {
            throw Validation::fail(
                'Validation.Field.InvalidOption',
                "O participante deve ser 'COMPRADOR', 'VENDEDOR' ou 'LOJA'.",
                'participante'
            );
        }

        $key = Validation::requireIdempotencyKey($idempotencyKey) ?? UuidGenerator::v4();

        $response = $this->executor->post(
            'v2/proposal/' . rawurlencode($id) . '/document',
            ProposalMapper::documentUploadToWire($upload),
            $key,
            $this->documentUploadAttemptTimeoutMs
        );
        ApiRequestExecutor::ensureSuccess($response);
    }
}
