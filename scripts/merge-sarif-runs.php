<?php

/**
 * Mescla múltiplas SARIF runs em uma só antes do upload.
 *
 * O GitHub Code Scanning recusa um results.sarif com mais de uma run sob a mesma
 * categoria (desde 2025-07-21). O Codacy CLI às vezes gera mais de uma run no mesmo
 * arquivo — este script combina rules e results de todas as runs numa única, sem
 * depender de adivinhar por que o Codacy as separa (mesma causa raiz e correção já
 * confirmadas nos SDKs Node e Python).
 */

declare(strict_types=1);

if ($argc !== 2) {
    fwrite(STDERR, "Uso: php merge-sarif-runs.php <arquivo.sarif>\n");
    exit(1);
}

$path = $argv[1];
$sarif = json_decode((string) file_get_contents($path), true);

$runs = $sarif['runs'] ?? [];
if (count($runs) <= 1) {
    echo sprintf("[merge-sarif-runs] %s já tem %d run(s), nada a fazer.\n", $path, count($runs));
    exit(0);
}

$first = array_shift($runs);
$rest = $runs;

$rulesById = [];
foreach ($first['tool']['driver']['rules'] ?? [] as $rule) {
    $rulesById[$rule['id']] = $rule;
}

$results = $first['results'] ?? [];

foreach ($rest as $run) {
    foreach ($run['tool']['driver']['rules'] ?? [] as $rule) {
        if (!isset($rulesById[$rule['id']])) {
            $rulesById[$rule['id']] = $rule;
        }
    }
    foreach ($run['results'] ?? [] as $result) {
        $results[] = $result;
    }
}

$first['tool']['driver']['rules'] = array_values($rulesById);
$first['results'] = $results;
$sarif['runs'] = [$first];

file_put_contents($path, json_encode($sarif));

echo sprintf(
    "[merge-sarif-runs] Mescladas %d runs em 1 (%d resultados) em %s.\n",
    1 + count($rest),
    count($results),
    $path
);
