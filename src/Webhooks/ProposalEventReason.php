<?php

declare(strict_types=1);

namespace Twila\Credicarro\Webhooks;

use Twila\Credicarro\Internal\Mapping\EnumLookup;

/**
 * O que disparou a notificação do webhook de proposta (`motivo_evento`).
 */
final class ProposalEventReason
{
    public const STATUS_CHANGED = 1;
    public const STEPS_CHANGED = 2;
    public const DOCUMENTS_CHANGED = 3;
    public const UNKNOWN = -1;

    private const NAMES = [
        self::STATUS_CHANGED => 'STATUS_ALTERADO',
        self::STEPS_CHANGED => 'ETAPAS_ALTERADAS',
        self::DOCUMENTS_CHANGED => 'DOCUMENTOS_ALTERADOS',
    ];

    private function __construct()
    {
    }

    public static function fromWireName(?string $name): int
    {
        return EnumLookup::fromName($name, self::NAMES);
    }

    public static function wireName(int $value): ?string
    {
        return EnumLookup::name(self::NAMES, $value);
    }
}
