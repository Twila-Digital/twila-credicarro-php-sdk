<?php

declare(strict_types=1);

namespace Twila\Credicarro\Webhooks;

/**
 * Contagem de envios em um intervalo do gráfico, no horário de Brasília.
 */
final class WebhookDeliveryChartPoint
{
    /** @var string início do intervalo: `10m` (10 minutos), `14h` (hora) ou `05` (dia), conforme o período (`rotulo`) */
    public $label;

    /** @var int envios com sucesso (`sucessos`) */
    public $successCount;

    /** @var int envios com erro (`erros`) */
    public $errorCount;

    public function __construct(string $label, int $successCount, int $errorCount)
    {
        $this->label = $label;
        $this->successCount = $successCount;
        $this->errorCount = $errorCount;
    }
}
