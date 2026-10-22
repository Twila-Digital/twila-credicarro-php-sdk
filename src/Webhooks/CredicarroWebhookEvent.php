<?php

declare(strict_types=1);

namespace Twila\Credicarro\Webhooks;

use InvalidArgumentException;
use SensitiveParameter;
use Twila\Credicarro\Errors\CredicarroWebhookSignatureException;
use Twila\Credicarro\Internal\Mapping\ProposalMapper;
use Twila\Credicarro\Internal\Mapping\Wire;
use Twila\Credicarro\Proposals\ProposalStatus;

/**
 * Verificação e leitura das notificações enviadas pela Credicarro ao seu endpoint.
 *
 * Valide SEMPRE contra o corpo bruto da requisição, antes de desserializar (reformatar o JSON invalida o hash).
 * O header é `X-CrediCarro-Signature: t={timestamp},v1={assinatura}`, onde `v1` é o HMAC-SHA256 em hexadecimal
 * minúsculo de `"{t}.{corpo bruto}"`, usando o `segredo_assinatura` do webhook (texto em UTF-8) como chave.
 */
final class CredicarroWebhookEvent
{
    public const DEFAULT_TOLERANCE_SECONDS = 300;

    private function __construct()
    {
    }

    /**
     * Verifica a assinatura e devolve o evento. Lança CredicarroWebhookSignatureException se a assinatura estiver
     * ausente, malformada, inválida ou fora da tolerância (nos dois sentidos), ou se o segredo for vazio.
     */
    public static function parse(
        string $rawBody,
        string $signatureHeader,
        #[SensitiveParameter]
        string $signingSecret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS
    ): ProposalWebhookEvent {
        $failure = self::signatureFailure($rawBody, $signatureHeader, $signingSecret, $toleranceSeconds, time());
        if ($failure !== null) {
            throw new CredicarroWebhookSignatureException($failure);
        }

        return self::parseUnverified($rawBody);
    }

    /**
     * Lê o evento SEM verificar a assinatura. Use só em testes; em produção use `parse()`.
     */
    public static function parseUnverified(string $rawBody): ProposalWebhookEvent
    {
        $wire = json_decode($rawBody, true);
        if (!is_array($wire)) {
            throw new CredicarroWebhookSignatureException('O corpo do webhook está vazio ou não é um JSON válido.');
        }

        $proposalId = Wire::nullableString($wire, 'id_proposta');
        $uuid = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/D';
        if ($proposalId === null || preg_match($uuid, $proposalId) !== 1) {
            throw new CredicarroWebhookSignatureException('O corpo do webhook não traz um id_proposta (UUID) válido.');
        }

        $statusRaw = Wire::nullableInt($wire, 'enum_status');
        $statusName = Wire::nullableString($wire, 'status');
        $reasonRaw = Wire::nullableString($wire, 'motivo_evento');

        $isDetailed = false;
        foreach (['motivo_evento', 'comprador', 'vendedor', 'loja'] as $key) {
            if (Wire::has($wire, $key)) {
                $isDetailed = true;
            }
        }

        return new ProposalWebhookEvent(
            $proposalId,
            $statusRaw !== null ? ProposalStatus::fromWireValue($statusRaw) : ProposalStatus::fromWireName($statusName),
            $statusRaw,
            $statusName,
            $reasonRaw !== null ? ProposalEventReason::fromWireName($reasonRaw) : null,
            $reasonRaw,
            Wire::nullableString($wire, 'motivo_recusa'),
            Wire::nullableString($wire, 'data_evento'),
            ProposalMapper::participantFromWire(Wire::object($wire, 'comprador'), 'cpf'),
            ProposalMapper::participantFromWire(Wire::object($wire, 'vendedor'), 'cpf'),
            ProposalMapper::participantFromWire(Wire::object($wire, 'loja'), 'cnpj'),
            $isDetailed
        );
    }

    /**
     * Devolve true se a assinatura for válida e estiver dentro da tolerância; qualquer problema de assinatura devolve
     * false. Só lança `\InvalidArgumentException` se a tolerância for negativa.
     *
     * @param int|null $now instante Unix de referência (para testes); por padrão, a hora atual
     */
    public static function verifySignature(
        string $rawBody,
        string $signatureHeader,
        #[SensitiveParameter]
        string $signingSecret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
        ?int $now = null
    ): bool {
        return self::signatureFailure($rawBody, $signatureHeader, $signingSecret, $toleranceSeconds, $now ?? time())
            === null;
    }

    /**
     * Calcula o `v1` esperado: HMAC-SHA256 hexadecimal minúsculo de `"{t}.{corpo}"`.
     *
     * @param int|string $timestamp o `t` do header, exatamente como recebido
     */
    public static function computeSignature(
        #[SensitiveParameter]
        string $signingSecret,
        $timestamp,
        string $rawBody
    ): string {
        return (string) hash_hmac('sha256', ((string) $timestamp) . '.' . $rawBody, $signingSecret);
    }

    /**
     * Compara o header `Authorization` recebido com o esperado (Basic ou Bearer configurado no cadastro do webhook)
     * em tempo constante. Um valor esperado vazio nunca é aceito.
     */
    public static function verifyAuthorization(string $receivedHeader, string $expectedHeader): bool
    {
        if ($expectedHeader === '') {
            return false;
        }

        return hash_equals(hash('sha256', $expectedHeader, true), hash('sha256', $receivedHeader, true));
    }

    private static function signatureFailure(
        string $rawBody,
        string $signatureHeader,
        #[SensitiveParameter]
        string $signingSecret,
        int $toleranceSeconds,
        int $now
    ): ?string {
        if ($toleranceSeconds < 0) {
            throw new InvalidArgumentException('A tolerância do webhook deve ser maior ou igual a zero.');
        }

        if ($signingSecret === '') {
            return 'O segredo de assinatura do webhook não foi informado.';
        }

        $parts = self::parseHeader($signatureHeader);
        if ($parts === null) {
            return 'O cabeçalho X-CrediCarro-Signature está ausente ou malformado.';
        }

        $computed = self::computeSignature($signingSecret, $parts['t'], $rawBody);
        if (!hash_equals($computed, strtolower($parts['v1']))) {
            return 'A assinatura do webhook não confere.';
        }

        if (abs($now - (int) $parts['t']) > $toleranceSeconds) {
            return 'O timestamp do webhook está fora da janela de tolerância — possível replay.';
        }

        return null;
    }

    /**
     * @return array{t: string, v1: string}|null
     */
    private static function parseHeader(string $header): ?array
    {
        $patterns = ['t' => '/^\d{1,10}$/', 'v1' => '/^[0-9a-fA-F]+$/'];
        $fields = [];

        foreach (explode(',', $header) as $part) {
            $pieces = explode('=', $part, 2);
            if (count($pieces) !== 2) {
                return null;
            }

            $key = trim($pieces[0]);
            if (!isset($patterns[$key])) {
                continue;
            }

            $value = trim($pieces[1]);
            if (isset($fields[$key]) || preg_match($patterns[$key], $value) !== 1) {
                return null;
            }
            $fields[$key] = $value;
        }

        if (!isset($fields['t'], $fields['v1'])) {
            return null;
        }

        return ['t' => $fields['t'], 'v1' => $fields['v1']];
    }
}
