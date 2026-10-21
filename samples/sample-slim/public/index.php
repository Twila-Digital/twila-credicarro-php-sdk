<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Factory\AppFactory;
use Twila\Credicarro\Config\ClientOptions;
use Twila\Credicarro\Config\Environment;
use Twila\Credicarro\CredicarroClient;
use Twila\Credicarro\Errors\CredicarroApiException;
use Twila\Credicarro\Webhooks\CredicarroWebhookEvent;

const MAX_WEBHOOK_BODY_BYTES = 1048576; // 1 MiB

/** O segredo é obrigatório: o sistema não sobe sem ele (e o SDK trata segredo vazio como assinatura inválida). */
function requireEnv(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        throw new RuntimeException('Variável de ambiente ausente: ' . $name);
    }

    return $value;
}

$app = AppFactory::create();

// CredicarroClient como singleton na aplicação — uma instância por processo/worker,
// nunca uma por requisição (ele mantém cache de token e estado do circuit breaker). Em PHP-FPM, passe um cache
// PSR-16 (APCu/Redis) como último argumento de ClientOptions para reaproveitar o token entre requisições.
$credicarroClient = new CredicarroClient(new ClientOptions(
    requireEnv('CREDICARRO_CLIENT_ID'),
    requireEnv('CREDICARRO_CLIENT_SECRET'),
    Environment::STAGING
));

$webhookSecret = requireEnv('CREDICARRO_WEBHOOK_SECRET');
$webhookAuthorization = getenv('CREDICARRO_WEBHOOK_AUTHORIZATION') ?: ''; // "Basic ..." ou "Bearer ..." (opcional)

// O que o seu sistema precisa enviar para a proposta avançar. Só lista: quem envia dados é o seu fluxo de negócio.
$app->get('/propostas/{id}/acoes-pendentes', function (
    ServerRequestInterface $request,
    ResponseInterface $response,
    array $args
) use ($credicarroClient) {
    try {
        $proposta = $credicarroClient->proposals->get($args['id']);
    } catch (CredicarroApiException $e) {
        $response->getBody()->write((string) json_encode(['erro' => $e->getErrorCode()]));

        return $response->withStatus($e->getStatusCode())->withHeader('Content-Type', 'application/json');
    }

    $acoes = array_map(static function ($acao) {
        return ['participante' => $acao->participant, 'acao' => $acao->kind, 'etapa' => $acao->step];
    }, $proposta->getPendingActions());

    $response->getBody()->write((string) json_encode($acoes));

    return $response->withHeader('Content-Type', 'application/json');
});

// Endpoint de webhook: limite de corpo, Authorization, assinatura contra o CORPO BRUTO, deduplicação, enfileiramento.
// Troque $seen e $queue por Redis/banco e fila reais; aqui são apenas ilustrativos (sem log de payload ou segredo).
$seen = [];
$queue = [];
$app->post('/webhooks/credicarro', function (ServerRequestInterface $request, ResponseInterface $response) use (
    $webhookSecret,
    $webhookAuthorization,
    &$seen,
    &$queue
) {
    // 1) Corpo de até 1 MiB (confere o tamanho declarado e lê no máximo 1 MiB + 1 byte).
    if ((int) $request->getHeaderLine('Content-Length') > MAX_WEBHOOK_BODY_BYTES) {
        return $response->withStatus(413);
    }

    // 2) Autenticação Basic/JWT configurada no cadastro, em tempo constante.
    if (
        $webhookAuthorization !== ''
        && !CredicarroWebhookEvent::verifyAuthorization($request->getHeaderLine('Authorization'), $webhookAuthorization)
    ) {
        return $response->withStatus(401);
    }

    // Leitura em laço, no máximo 1 MiB + 1 byte (um único read() pode devolver menos do que o corpo inteiro).
    $corpoBruto = Utils::copyToString($request->getBody(), MAX_WEBHOOK_BODY_BYTES + 1);
    if (strlen($corpoBruto) > MAX_WEBHOOK_BODY_BYTES) {
        return $response->withStatus(413);
    }

    // 3) Assinatura (HMAC-SHA256, janela de 5 minutos): inválida responde 401.
    if (
        !CredicarroWebhookEvent::verifySignature(
            $corpoBruto,
            $request->getHeaderLine('X-CrediCarro-Signature'),
            $webhookSecret
        )
    ) {
        return $response->withStatus(401);
    }

    // 4) Deduplicação pela Idempotency-Key da entrega (chave de 1 a 200 caracteres).
    $chave = $request->getHeaderLine('Idempotency-Key');
    if ($chave !== '' && strlen($chave) > 200) {
        return $response->withStatus(400);
    }

    if ($chave !== '' && isset($seen[$chave])) {
        return $response->withStatus(204); // entrega repetida
    }

    // 5) Só responde 2xx depois de enfileirar; se falhar, desfaz a deduplicação e responde 503 (a Credicarro retenta).
    try {
        if ($chave !== '') {
            $seen[$chave] = true;
        }
        $queue[] = $corpoBruto; // processe no worker, nunca na requisição (limite de 15 s por entrega)
    } catch (Throwable $e) {
        unset($seen[$chave]);
        error_log('Falha ao enfileirar webhook | erro: ' . get_class($e));

        return $response->withStatus(503);
    }

    return $response->withStatus(202);
});

$app->run();
