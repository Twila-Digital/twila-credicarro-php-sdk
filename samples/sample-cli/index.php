<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Twila\Credicarro\Config\ClientOptions;
use Twila\Credicarro\Config\Environment;
use Twila\Credicarro\CredicarroClient;
use Twila\Credicarro\Errors\CredicarroApiException;
use Twila\Credicarro\ListRequest;

$clientId = getenv('CREDICARRO_CLIENT_ID');
$clientSecret = getenv('CREDICARRO_CLIENT_SECRET');

if ($clientId === false || $clientSecret === false) {
    fwrite(STDERR, "Defina CREDICARRO_CLIENT_ID e CREDICARRO_CLIENT_SECRET no ambiente.\n");
    exit(1);
}

$client = new CredicarroClient(new ClientOptions($clientId, $clientSecret, Environment::STAGING));

try {
    $page = $client->proposals->list(new ListRequest(1, 10));
} catch (CredicarroApiException $e) {
    fwrite(STDERR, sprintf("%d %s: %s\n", $e->getStatusCode(), (string) $e->getErrorCode(), $e->getMessage()));
    exit(1);
}

printf("%d proposta(s) no total (página %d)%s", $page->totalCount, $page->page, PHP_EOL);

foreach ($page->items as $summary) {
    printf("#%d %s - %s%s", $summary->number, $summary->id, (string) $summary->statusName, PHP_EOL);

    try {
        $details = $client->proposals->get($summary->id);
    } catch (CredicarroApiException $e) {
        continue;
    }

    foreach ($details->getPendingActions() as $action) {
        printf("  - %s: %s (etapa %d)%s", $action->participant, $action->kind, $action->step, PHP_EOL);
    }
}
