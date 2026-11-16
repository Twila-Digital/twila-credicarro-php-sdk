<?php

declare(strict_types=1);

namespace Twila\Credicarro\Simulations;

/**
 * Uma opção da simulação de financiamento sem CPF (um prazo). Não tem `simulationId`: nada é gravado e a opção não
 * serve para criar proposta.
 */
final class FinancingWithoutCpfOption
{
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

    /** @var float entrada mínima da loja para o veículo neste prazo (`valor_entrada_minima`) */
    public $minimumDownPayment;

    /** @var float|null `valor_retencao_diaria_aproximado` — omitido quando não se aplica */
    public $approximateDailyRetention;

    public function __construct(
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
        float $minimumDownPayment,
        ?float $approximateDailyRetention = null
    ) {
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
        $this->minimumDownPayment = $minimumDownPayment;
        $this->approximateDailyRetention = $approximateDailyRetention;
    }
}
