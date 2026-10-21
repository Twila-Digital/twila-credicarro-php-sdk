<?php

declare(strict_types=1);

namespace Twila\Credicarro\V1;

use Twila\Credicarro\Internal\Http\ApiRequestExecutor;
use Twila\Credicarro\Internal\Http\ResponseReader;
use Twila\Credicarro\Internal\Mapping\ProposalMapper;
use Twila\Credicarro\Internal\Validation;
use Twila\Credicarro\Proposals\CreateProposalRequest;
use Twila\Credicarro\Proposals\ProposalSummary;

/**
 * Rotas v1 de proposta. Propostas criadas pela v1 não têm etapas nem documentos pela API.
 */
final class V1ProposalsClient
{
    /** @var ApiRequestExecutor */
    private $executor;

    public function __construct(ApiRequestExecutor $executor)
    {
        $this->executor = $executor;
    }

    /**
     * Cria a proposta (`POST v1/proposal`). Recebe Idempotency-Key automática. Devolve o `id_proposta`.
     */
    public function create(CreateProposalRequest $request): string
    {
        Validation::requireUuid($request->simulationId, 'simulacaoId');

        $response = $this->executor->post('v1/proposal', ProposalMapper::createRequestToWire($request));
        ApiRequestExecutor::ensureSuccess($response);

        return ResponseReader::requiredString($response, 'id_proposta');
    }

    /**
     * Consulta uma proposta pelo ID (`GET v1/proposal/{id}`).
     */
    public function get(string $proposalId): ProposalSummary
    {
        $id = Validation::requireUuid($proposalId, 'propostaId');

        $response = $this->executor->get('v1/proposal/' . rawurlencode($id));
        ApiRequestExecutor::ensureSuccess($response);

        return ProposalMapper::summaryFromWire(ResponseReader::object($response));
    }
}
