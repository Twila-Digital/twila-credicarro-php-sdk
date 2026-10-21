<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Auth;

/**
 * Credenciais do cliente de integração. O segredo vive só dentro deste objeto (propriedade privada, sem getter) e
 * nunca é passado como argumento de método: assim não aparece em stack traces (`zend.exception_ignore_args=0`),
 * `var_dump`, `print_r`, `json_encode`, `serialize` nem `var_export`.
 *
 * @internal
 */
final class Credentials implements \JsonSerializable
{
    /** @var string */
    private $clientId;

    /** @var \Closure():string o segredo fica dentro de uma closure: var_export/print_r não o exibem */
    private $clientSecret;

    public function __construct(
        string $clientId,
        #[\SensitiveParameter]
        string $clientSecret
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = static function () use ($clientSecret): string {
            return $clientSecret;
        };
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    public function isClientIdBlank(): bool
    {
        return trim($this->clientId) === '';
    }

    public function isClientSecretBlank(): bool
    {
        return trim(($this->clientSecret)()) === '';
    }

    /**
     * Corpo da requisição de token. Devolve um array (e não uma string) para que, em stack traces, o valor apareça
     * apenas como "Array".
     *
     * @return array<string, string>
     */
    public function toTokenRequestBody(): array
    {
        return ['clientId' => $this->clientId, 'clientSecret' => ($this->clientSecret)()];
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['clientId' => $this->clientId, 'clientSecret' => '***'];
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
