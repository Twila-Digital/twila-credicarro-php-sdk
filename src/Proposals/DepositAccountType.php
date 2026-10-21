<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

/**
 * Tipo da conta de recebimento (`tipoConta`). O valor é o texto da API.
 */
final class DepositAccountType
{
    public const CHECKING = 'CORRENTE';
    public const SAVINGS = 'POUPANCA';
    public const PAYMENT = 'PAGAMENTO';

    private function __construct()
    {
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [self::CHECKING, self::SAVINGS, self::PAYMENT];
    }
}
