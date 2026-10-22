<?php

declare(strict_types=1);

namespace Twila\Credicarro;

use GuzzleHttp\ClientInterface;
use Twila\Credicarro\Config\ClientOptions;
use Twila\Credicarro\Config\ClientOptionsResolver;
use Twila\Credicarro\Customers\CustomersClient;
use Twila\Credicarro\Internal\Auth\AccessTokenProvider;
use Twila\Credicarro\Internal\Auth\TokenApiClient;
use Twila\Credicarro\Internal\Http\ApiRequestExecutor;
use Twila\Credicarro\Internal\Resilience\ResiliencePipeline;
use Twila\Credicarro\Proposals\ProposalsClient;
use Twila\Credicarro\Simulations\SimulationsClient;
use Twila\Credicarro\V1\V1Client;
use Twila\Credicarro\Webhooks\WebhooksClient;

/**
 * Cliente principal do SDK. Reaproveite como singleton na aplicação (não crie um por requisição) —
 * ele mantém o cache do token de acesso e o estado do circuit breaker.
 */
final class CredicarroClient
{
    /** @var CustomersClient */
    public $customers;

    /** @var SimulationsClient */
    public $simulations;

    /** @var ProposalsClient */
    public $proposals;

    /** @var WebhooksClient */
    public $webhooks;

    /** @var V1Client */
    public $v1;

    /**
     * @param ClientInterface|null $httpClient Cliente Guzzle customizado — uso interno/testes.
     *   Consumidores normais não precisam informar isso.
     */
    public function __construct(ClientOptions $options, ?ClientInterface $httpClient = null)
    {
        $resolved = ClientOptionsResolver::resolve($options);

        $tokenApiClient = new TokenApiClient(
            $resolved->baseUrl,
            $resolved->resilience->attemptTimeoutMs,
            new ResiliencePipeline($resolved->resilience),
            $httpClient
        );
        $tokenProvider = new AccessTokenProvider(
            $tokenApiClient,
            $resolved->credentials,
            $resolved->tokenCache,
            $resolved->environment . '|' . $resolved->baseUrl
        );

        $executor = new ApiRequestExecutor($resolved->baseUrl, $tokenProvider, $resolved->resilience, $httpClient);

        $this->customers = new CustomersClient($executor);
        $this->simulations = new SimulationsClient($executor);
        $this->proposals = new ProposalsClient($executor, $resolved->resilience->documentUploadAttemptTimeoutMs);
        $this->webhooks = new WebhooksClient($executor);
        $this->v1 = new V1Client($executor);
    }
}
