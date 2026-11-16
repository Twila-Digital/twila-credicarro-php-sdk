<?php

declare(strict_types=1);

namespace Twila\Credicarro\Webhooks;

/**
 * Filtros do histórico (`listDeliveries`) e do gráfico (`getDeliveryChart`) de envios. Campos `null` não são enviados.
 *
 * As datas são texto ISO-8601: uma data sem horário (`2026-11-05`) vale pelo dia inteiro; com horário, informe o fuso
 * (`2026-11-05T14:00:00-03:00`). No gráfico, o período é obrigatório e pode ter até 31 dias.
 */
final class WebhookDeliveryFilter
{
    /** @var string|null início do período (`data_inicial`) */
    public $initialDate;

    /** @var string|null fim do período (`data_final`) */
    public $finalDate;

    /** @var string|null `id_proposta` */
    public $proposalId;

    /** @var string|null `id_simulacao` */
    public $simulationId;

    /** @var string|null `numero_proposta`, até 50 caracteres */
    public $proposalNumber;

    /** @var int|null status HTTP devolvido pela sua URL, de 100 a 599 (`status_http`) */
    public $statusCode;

    public function __construct(
        ?string $initialDate = null,
        ?string $finalDate = null,
        ?string $proposalId = null,
        ?string $simulationId = null,
        ?string $proposalNumber = null,
        ?int $statusCode = null
    ) {
        $this->initialDate = $initialDate;
        $this->finalDate = $finalDate;
        $this->proposalId = $proposalId;
        $this->simulationId = $simulationId;
        $this->proposalNumber = $proposalNumber;
        $this->statusCode = $statusCode;
    }
}
