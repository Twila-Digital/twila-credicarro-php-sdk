<?php

declare(strict_types=1);

namespace Twila\Credicarro\Customers;

use Twila\Credicarro\Internal\Masking\MasksSensitiveData;

final class ProfessionalData
{
    use MasksSensitiveData;

    /** @var int um dos OccupationType::* (`tipoOcupacao`) */
    public $occupation;

    /** @var float renda mensal em reais (`renda`), maior que zero */
    public $monthlyIncome;

    public function __construct(int $occupation, float $monthlyIncome)
    {
        $this->occupation = $occupation;
        $this->monthlyIncome = $monthlyIncome;
    }

    /**
     * @return array<string, string>
     */
    protected function sensitiveFields(): array
    {
        return [
            'monthlyIncome' => 'full',
        ];
    }
}
