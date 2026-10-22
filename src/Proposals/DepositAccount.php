<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

use Twila\Credicarro\Internal\Masking\MasksSensitiveData;

/**
 * Conta de recebimento de uma proposta de refinanciamento. O titular deve ser o cliente da proposta.
 */
final class DepositAccount
{
    use MasksSensitiveData;

    /** @var string CPF do titular (`cpfTitular`) */
    public $holderCpf;

    /** @var string código do banco (COMPE) com 3 dígitos (`codigoBanco`) */
    public $bankCode;

    /** @var string agência, até 4 dígitos, sem o dígito (`agencia`) */
    public $branch;

    /** @var string dígito da agência, número ou X (`digitoAgencia`) */
    public $branchDigit;

    /** @var string conta, de 4 a 13 dígitos, sem o dígito (`conta`) */
    public $accountNumber;

    /** @var string dígito da conta, número ou X (`digitoConta`) */
    public $accountDigit;

    /** @var string um dos DepositAccountType::* (`tipoConta`) */
    public $accountType;

    public function __construct(
        #[\SensitiveParameter]
        string $holderCpf,
        string $bankCode,
        string $branch,
        string $branchDigit,
        string $accountNumber,
        string $accountDigit,
        string $accountType
    ) {
        $this->holderCpf = $holderCpf;
        $this->bankCode = $bankCode;
        $this->branch = $branch;
        $this->branchDigit = $branchDigit;
        $this->accountNumber = $accountNumber;
        $this->accountDigit = $accountDigit;
        $this->accountType = $accountType;
    }

    /**
     * @return array<string, string>
     */
    protected function sensitiveFields(): array
    {
        return [
            'holderCpf' => 'cpf',
            'branch' => 'full',
            'branchDigit' => 'full',
            'accountNumber' => 'full',
            'accountDigit' => 'full',
        ];
    }
}
