<?php

declare(strict_types=1);

namespace Twila\Credicarro\Errors;

/**
 * Item da lista `erros` do ProblemDetails (JSON malformado ou valor de tipo errado).
 */
final class ProblemError
{
    /** @var string|null */
    public $code;

    /** @var string|null */
    public $message;

    /** @var string|null */
    public $field;

    public function __construct(?string $code = null, ?string $message = null, ?string $field = null)
    {
        $this->code = $code;
        $this->message = $message;
        $this->field = $field;
    }
}
