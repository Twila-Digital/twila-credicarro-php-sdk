<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

use Twila\Credicarro\Internal\Masking\MasksSensitiveData;

final class CreateProposalRequest
{
    use MasksSensitiveData;

    /** @var string CPF do cliente, com ou sem pontuação (`cpf`) */
    public $cpf;

    /** @var string `id_simulacao` da opção escolhida (`simulacaoId`) */
    public $simulationId;

    public function __construct(
        #[\SensitiveParameter]
        string $cpf,
        string $simulationId
    ) {
        $this->cpf = $cpf;
        $this->simulationId = $simulationId;
    }

    /**
     * @return array<string, string>
     */
    protected function sensitiveFields(): array
    {
        return [
            'cpf' => 'cpf',
        ];
    }
}
