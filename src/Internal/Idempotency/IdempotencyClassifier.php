<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Idempotency;

/**
 * Decide quais rotas recebem Idempotency-Key automática e quais chamadas são seguras para repetição.
 *
 * Diferente de outros SDKs da Twila: `POST v1/customer/unified` NUNCA recebe chave automática — com a chave a rota só
 * cria (CPF existente devolve 409); sem a chave ela é um upsert. Sem chave informada pelo chamador, não é repetida.
 *
 * @internal
 */
final class IdempotencyClassifier
{
    private const AUTOMATIC_KEY_PATTERNS = [
        '#^v1/customer$#',
        '#^v1/customer/address$#',
        '#^v1/customer/professional$#',
        '#^v[12]/proposal/simulation/(financing|refinancing)$#',
        '#^v[12]/proposal$#',
        '#^v2/proposal/[^/]+/document$#',
    ];

    private const REPLACE_SEMANTICS_PATTERNS = [
        '#^v2/proposal/[^/]+/(vehicle-detail|deposit-account)$#',
    ];

    private const TOKEN_PATH = 'v1/authentication/accesstoken';

    private const WEBHOOK_ITEM_PATTERN = '#^v1/webhook/[^/]+$#';

    public static function requiresIdempotencyKey(string $method, string $path): bool
    {
        if (strtoupper($method) !== 'POST') {
            return false;
        }

        return self::matchesAny($path, self::AUTOMATIC_KEY_PATTERNS);
    }

    public static function isRetrySafe(string $method, string $path, bool $hasIdempotencyKey): bool
    {
        $upperMethod = strtoupper($method);

        if (in_array($upperMethod, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        if (in_array($upperMethod, ['PUT', 'DELETE'], true)) {
            return self::matchesAny($path, [self::WEBHOOK_ITEM_PATTERN]);
        }

        if ($upperMethod !== 'POST') {
            return false;
        }

        return $hasIdempotencyKey
            || $path === self::TOKEN_PATH
            || self::matchesAny($path, self::REPLACE_SEMANTICS_PATTERNS);
    }

    /**
     * @param array<int, non-empty-string> $patterns
     */
    private static function matchesAny(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
