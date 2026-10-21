<?php

declare(strict_types=1);

namespace Twila\Credicarro\Config;

use Psr\SimpleCache\CacheInterface;
use Twila\Credicarro\Internal\Auth\Credentials;

/**
 * Opções do `CredicarroClient`. O `clientSecret` é guardado em um objeto interno mascarado: não há getter para ele
 * e ele não aparece em `var_dump`, `print_r`, `json_encode`, `serialize` nem stack traces.
 */
final class ClientOptions implements \JsonSerializable
{
    /** @var Credentials */
    private $credentials;

    /** @var string */
    private $environment;

    /** @var string|null */
    private $baseUrl;

    /** @var ResilienceOptions */
    private $resilience;

    /** @var CacheInterface|null */
    private $tokenCache;

    /**
     * @param CacheInterface|null $tokenCache cache PSR-16 (APCu, Redis, Memcached...) para compartilhar o token de
     *   acesso entre requisições/processos (PHP-FPM cria um processo por requisição, sem memória compartilhada).
     *   O padrão é só em memória, por instância do cliente. O cache guarda o token (um segredo de curta duração)
     *   e nunca o `clientSecret`; use um backend que só a sua aplicação consiga ler.
     */
    public function __construct(
        string $clientId,
        #[\SensitiveParameter]
        string $clientSecret,
        string $environment = Environment::PRODUCTION,
        ?string $baseUrl = null,
        ?ResilienceOptions $resilience = null,
        ?CacheInterface $tokenCache = null
    ) {
        $this->credentials = new Credentials($clientId, $clientSecret);
        $this->environment = $environment;
        $this->baseUrl = $baseUrl;
        $this->resilience = $resilience ?? new ResilienceOptions();
        $this->tokenCache = $tokenCache;
    }

    public function getClientId(): string
    {
        return $this->credentials->clientId();
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    public function getResilience(): ResilienceOptions
    {
        return $this->resilience;
    }

    public function getTokenCache(): ?CacheInterface
    {
        return $this->tokenCache;
    }

    /**
     * @internal
     */
    public function credentials(): Credentials
    {
        return $this->credentials;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'clientId' => $this->credentials->clientId(),
            'clientSecret' => '***',
            'environment' => $this->environment,
            'baseUrl' => $this->baseUrl,
            'resilience' => $this->resilience,
            'tokenCache' => $this->tokenCache === null ? null : get_class($this->tokenCache),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    /**
     * Recusa a serialização: este objeto guarda segredos e não pode ir para fila, sessão ou cache.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new \LogicException('Este objeto guarda credenciais e não pode ser serializado.');
    }

    /**
     * @param array<string, mixed> $data
     * @psalm-suppress UnusedParam
     */
    public function __unserialize(array $data): void
    {
        throw new \LogicException('Este objeto guarda credenciais e não pode ser desserializado.');
    }
}
