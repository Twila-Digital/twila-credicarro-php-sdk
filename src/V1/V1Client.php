<?php

declare(strict_types=1);

namespace Twila\Credicarro\V1;

use Twila\Credicarro\Internal\Http\ApiRequestExecutor;

/**
 * Rotas v1 de simulação e proposta, sem `cnpjLoja`. Para novas integrações prefira a v2 (`$client->simulations` e
 * `$client->proposals`).
 */
final class V1Client
{
    /** @var V1SimulationsClient */
    public $simulations;

    /** @var V1ProposalsClient */
    public $proposals;

    public function __construct(ApiRequestExecutor $executor)
    {
        $this->simulations = new V1SimulationsClient($executor);
        $this->proposals = new V1ProposalsClient($executor);
    }
}
