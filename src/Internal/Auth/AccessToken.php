<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Auth;

/**
 * @internal
 */
final class AccessToken implements \JsonSerializable
{
    /** @var \Closure():string o token fica dentro de uma closure: var_export/print_r não o exibem */
    private $value;

    /** @var int instante Unix (UTC) até quando o token pode ser reaproveitado */
    private $expiresAt;

    public function __construct(
        #[\SensitiveParameter]
        string $value,
        int $expiresAt
    ) {
        $this->value = static function () use ($value): string {
            return $value;
        };
        $this->expiresAt = $expiresAt;
    }

    public function value(): string
    {
        return ($this->value)();
    }

    public function expiresAt(): int
    {
        return $this->expiresAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['value' => '***', 'expiresAt' => $this->expiresAt];
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
