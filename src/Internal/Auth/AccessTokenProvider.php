<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Auth;

use Psr\SimpleCache\CacheInterface;

/**
 * Mantém o token em memória (por instância) e, opcionalmente, em um cache PSR-16 compartilhado, até o `expira_em`.
 * O PHP executa de forma síncrona por processo, então a renovação é naturalmente "single-flight" na instância.
 * O cache guarda só o token e a validade, nunca o clientSecret; falhas do cache são ignoradas (cai para a API).
 *
 * @internal
 */
final class AccessTokenProvider
{
    /** @var TokenApiClient */
    private $tokenApiClient;

    /** @var Credentials */
    private $credentials;

    /** @var CacheInterface|null */
    private $cache;

    /** @var string */
    private $cacheKey;

    /** @var AccessToken|null */
    private $cached;

    /**
     * @param string $scope identifica o ambiente/URL base; entra (com o clientId) no hash da chave de cache
     */
    public function __construct(
        TokenApiClient $tokenApiClient,
        Credentials $credentials,
        ?CacheInterface $cache = null,
        string $scope = ''
    ) {
        $this->tokenApiClient = $tokenApiClient;
        $this->credentials = $credentials;
        $this->cache = $cache;
        $this->cacheKey = 'credicarro_token_' . hash('sha256', $scope . '|' . $credentials->clientId());
    }

    public function getToken(): string
    {
        if ($this->cached !== null && time() < $this->cached->expiresAt()) {
            return $this->cached->value();
        }

        $this->cached = null;

        $shared = $this->readSharedToken();
        if ($shared !== null) {
            $this->cached = $shared;

            return $shared->value();
        }

        $token = $this->tokenApiClient->generate($this->credentials);
        $this->cached = $token;
        $this->writeSharedToken($token);

        return $token->value();
    }

    /**
     * Descarta o token rejeitado (memória e cache compartilhado) — só se ele ainda for o atual, para não jogar fora
     * um token que já foi renovado.
     */
    public function invalidate(
        #[\SensitiveParameter]
        string $rejectedToken
    ): void {
        if ($this->cached !== null && hash_equals($this->cached->value(), $rejectedToken)) {
            $this->cached = null;
        }

        $shared = $this->readSharedToken();
        if ($this->cache !== null && $shared !== null && hash_equals($shared->value(), $rejectedToken)) {
            try {
                $this->cache->delete($this->cacheKey);
            } catch (\Throwable $ignored) {
                return;
            }
        }
    }

    private function readSharedToken(): ?AccessToken
    {
        if ($this->cache === null) {
            return null;
        }

        try {
            $entry = $this->cache->get($this->cacheKey);
        } catch (\Throwable $ignored) {
            return null;
        }

        if (
            !is_array($entry)
            || !isset($entry['v'], $entry['e'])
            || !is_string($entry['v'])
            || $entry['v'] === ''
            || !is_int($entry['e'])
            || $entry['e'] <= time()
        ) {
            return null;
        }

        return new AccessToken($entry['v'], $entry['e']);
    }

    private function writeSharedToken(AccessToken $token): void
    {
        if ($this->cache === null) {
            return;
        }

        $ttl = $token->expiresAt() - time();
        if ($ttl <= 0) {
            return;
        }

        try {
            $this->cache->set($this->cacheKey, ['v' => $token->value(), 'e' => $token->expiresAt()], $ttl);
        } catch (\Throwable $ignored) {
            return;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['cached' => $this->cached === null ? null : '***', 'shared' => $this->cache !== null];
    }
}
