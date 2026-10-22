<?php

declare(strict_types=1);

namespace Twila\Credicarro\Errors;

/**
 * Corpo de erro da API: `tipo`, `titulo`, `status`, `detalhe`, `campo` e `erros[{codigo, mensagem, campo}]`.
 * Falhas inesperadas podem vir no formato padrão (`type`, `title`, `status`, `detail`), também aceito.
 */
final class ProblemDetails
{
    /** @var string|null */
    public $type;

    /** @var string|null */
    public $title;

    /** @var int|null */
    public $status;

    /** @var string|null */
    public $detail;

    /** @var string|null */
    public $field;

    /** @var array<int, ProblemError> */
    public $errors;

    /**
     * @param array<int, ProblemError> $errors
     */
    public function __construct(
        ?string $type = null,
        ?string $title = null,
        ?int $status = null,
        ?string $detail = null,
        ?string $field = null,
        array $errors = []
    ) {
        $this->type = $type;
        $this->title = $title;
        $this->status = $status;
        $this->detail = $detail;
        $this->field = $field;
        $this->errors = $errors;
    }

    /**
     * @param mixed $body
     */
    public static function parse($body): self
    {
        if (!is_array($body)) {
            return new self();
        }

        $errors = [];
        if (isset($body['erros']) && is_array($body['erros'])) {
            foreach ($body['erros'] as $item) {
                if (is_array($item)) {
                    $errors[] = new ProblemError(
                        self::stringOrNull($item, 'codigo'),
                        self::stringOrNull($item, 'mensagem'),
                        self::stringOrNull($item, 'campo')
                    );
                }
            }
        }

        $status = $body['status'] ?? null;

        return new self(
            self::firstString($body, 'tipo', 'type'),
            self::firstString($body, 'titulo', 'title'),
            is_int($status) ? $status : null,
            self::firstString($body, 'detalhe', 'detail'),
            self::stringOrNull($body, 'campo'),
            $errors
        );
    }

    /**
     * @param array<mixed> $source
     */
    private static function stringOrNull(array $source, string $key): ?string
    {
        return isset($source[$key]) && is_string($source[$key]) && $source[$key] !== '' ? $source[$key] : null;
    }

    /**
     * @param array<mixed> $source
     */
    private static function firstString(array $source, string $primary, string $fallback): ?string
    {
        $value = self::stringOrNull($source, $primary);

        return $value ?? self::stringOrNull($source, $fallback);
    }
}
