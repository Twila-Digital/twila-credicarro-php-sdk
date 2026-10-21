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

const MAX_WEBHOOK_BODY_BYTES = 1048576;

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

$credicarroClient = new CredicarroClient(new ClientOptions(
    requireEnv('CREDICARRO_CLIENT_ID'),
    requireEnv('CREDICARRO_CLIENT_SECRET'),
    Environment::STAGING
));

$webhookSecret = requireEnv('CREDICARRO_WEBHOOK_SECRET');
$webhookAuthorization = getenv('CREDICARRO_WEBHOOK_AUTHORIZATION') ?: '';

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

$seen = [];
$queue = [];
$app->post('/webhooks/credicarro', function (ServerRequestInterface $request, ResponseInterface $response) use (
    $webhookSecret,
    $webhookAuthorization,
    &$seen,
    &$queue
) {
    if ((int) $request->getHeaderLine('Content-Length') > MAX_WEBHOOK_BODY_BYTES) {
        return $response->withStatus(413);
    }

    if (
        $webhookAuthorization !== ''
        && !CredicarroWebhookEvent::verifyAuthorization($request->getHeaderLine('Authorization'), $webhookAuthorization)
    ) {
        return $response->withStatus(401);
    }

    $corpoBruto = Utils::copyToString($request->getBody(), MAX_WEBHOOK_BODY_BYTES + 1);
    if (strlen($corpoBruto) > MAX_WEBHOOK_BODY_BYTES) {
        return $response->withStatus(413);
    }

    if (
        !CredicarroWebhookEvent::verifySignature(
            $corpoBruto,
            $request->getHeaderLine('X-CrediCarro-Signature'),
            $webhookSecret
        )
    ) {
        return $response->withStatus(401);
    }

    $chave = $request->getHeaderLine('Idempotency-Key');
    if ($chave !== '' && strlen($chave) > 200) {
        return $response->withStatus(400);
    }

    if ($chave !== '' && isset($seen[$chave])) {
        return $response->withStatus(204);
    }

    try {
        if ($chave !== '') {
            $seen[$chave] = true;
        }
        $queue[] = $corpoBruto;
    } catch (Throwable $e) {
        unset($seen[$chave]);
        error_log('Falha ao enfileirar webhook | erro: ' . get_class($e));

        return $response->withStatus(503);
    }

    return $response->withStatus(202);
});

$app->run();
