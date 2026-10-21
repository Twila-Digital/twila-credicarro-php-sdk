<?php

declare(strict_types=1);

namespace Twila\Credicarro\Errors;

/**
 * `422` — regra de negócio não atendida. Trate pelo código (`getErrorCode()`), por exemplo
 * `Store.Integration.Intermediation` ou `Customer.Account.Incomplete`.
 */
final class CredicarroBusinessRuleException extends CredicarroApiException
{
    public function __construct(string $message, ProblemDetails $problemDetails, ?string $rawBody = null)
    {
        parent::__construct($message, 422, $problemDetails, $rawBody);
    }
}
