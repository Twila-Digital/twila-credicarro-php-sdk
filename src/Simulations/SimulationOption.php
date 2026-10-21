<?php

declare(strict_types=1);

namespace Twila\Credicarro\Simulations;

/**
 * Uma opção de simulação (um prazo). Use `simulationId` ao criar a proposta.
 */
final class SimulationOption
{
    /** @var string `id_simulacao` */
    public $simulationId;

    /** @var float `valor_solicitado` */
    public $requestedAmount;

    /** @var int prazo em meses (`prazo`) */
    public $term;

    /** @var float `valor_parcela` */
    public $installmentAmount;

    /** @var float `valor_tac` */
    public $tacAmount;

    /** @var float `valor_boleto` */
    public $boletoAmount;

    /** @var float `valor_seguro` */
    public $insuranceAmount;

    /** @var float `valor_iof` */
    public $iofAmount;

    /** @var float `percentual_iof` */
    public $iofRate;

    /** @var float `valor_credito_total` */
    public $totalCreditAmount;

    /** @var float `valor_divida_total` */
    public $totalDebtAmount;

    /** @var float `percentual_cet_mensal` */
    public $monthlyCet;

    /** @var float `percentual_cet_anual` */
    public $annualCet;

    /** @var float `percentual_juros_mensal` */
    public $monthlyInterestRate;

    /** @var float `percentual_juros_anual` */
    public $annualInterestRate;

    /** @var float|null `valor_retencao_diaria_aproximado` — omitido quando não se aplica */
    public $approximateDailyRetention;

    public function __construct(
        string $simulationId,
        float $requestedAmount,
        int $term,
        float $installmentAmount,
        float $tacAmount,
        float $boletoAmount,
        float $insuranceAmount,
        float $iofAmount,
        float $iofRate,
        float $totalCreditAmount,
        float $totalDebtAmount,
        float $monthlyCet,
        float $annualCet,
        float $monthlyInterestRate,
        float $annualInterestRate,
        ?float $approximateDailyRetention = null
    ) {
        $this->simulationId = $simulationId;
        $this->requestedAmount = $requestedAmount;
        $this->term = $term;
        $this->installmentAmount = $installmentAmount;
        $this->tacAmount = $tacAmount;
        $this->boletoAmount = $boletoAmount;
        $this->insuranceAmount = $insuranceAmount;
        $this->iofAmount = $iofAmount;
        $this->iofRate = $iofRate;
        $this->totalCreditAmount = $totalCreditAmount;
        $this->totalDebtAmount = $totalDebtAmount;
        $this->monthlyCet = $monthlyCet;
        $this->annualCet = $annualCet;
        $this->monthlyInterestRate = $monthlyInterestRate;
        $this->annualInterestRate = $annualInterestRate;
        $this->approximateDailyRetention = $approximateDailyRetention;
    }
}
