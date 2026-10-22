<?php

declare(strict_types=1);

namespace Twila\Credicarro\Config;

use Psr\SimpleCache\CacheInterface;
use Twila\Credicarro\Internal\Auth\Credentials;

/**
 * @internal
 */
final class ResolvedClientOptions
{
    /** @var Credentials */
    public $credentials;

    /** @var string */
    public $environment;

    /** @var string */
    public $baseUrl;

    /** @var ResilienceOptions */
    public $resilience;

    /** @var CacheInterface|null */
    public $tokenCache;

    public function __construct(
        Credentials $credentials,
        string $environment,
        string $baseUrl,
        ResilienceOptions $resilience,
        ?CacheInterface $tokenCache
    ) {
        $this->credentials = $credentials;
        $this->environment = $environment;
        $this->baseUrl = $baseUrl;
        $this->resilience = $resilience;
        $this->tokenCache = $tokenCache;
    }
}
