<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

use Twila\Credicarro\Internal\Mapping\EnumLookup;

/**
 * Status da proposta. Pseudo-enum (constantes inteiras) porque o SDK suporta PHP 7.4, que não tem `enum` nativo.
 * Códigos que o SDK não conhece viram `UNKNOWN`; o código original fica em `statusRaw`.
 */
final class ProposalStatus
{
    public const ANALYSING = 1;
    public const APPROVED = 2;
    public const REFUSED = 3;
    public const CANCELED = 4;
    public const MANUAL_ANALYSIS = 5;
    public const COMPLETED = 6;
    public const IN_NEGOTIATION = 7;
    public const EXPIRED = 8;
    public const PENDING = 9;
    public const UNKNOWN = -1;

    /** Nomes do payload v2 e da consulta v2 (português). */
    private const NAMES = [
        self::ANALYSING => 'EM_ANALISE',
        self::APPROVED => 'APROVADA',
        self::REFUSED => 'RECUSADA',
        self::CANCELED => 'CANCELADA',
        self::MANUAL_ANALYSIS => 'ANALISE_MANUAL',
        self::COMPLETED => 'FINALIZADA',
        self::IN_NEGOTIATION => 'EM_NEGOCIACAO',
        self::EXPIRED => 'EXPIRADA',
        self::PENDING => 'PENDENTE',
    ];

    /** Nomes do payload v1 e da consulta v1 (inglês). */
    private const ENGLISH_NAMES = [
        self::ANALYSING => 'Analysing',
        self::APPROVED => 'Approved',
        self::REFUSED => 'Refused',
        self::CANCELED => 'Canceled',
        self::MANUAL_ANALYSIS => 'ManualAnalysis',
        self::COMPLETED => 'Completed',
        self::IN_NEGOTIATION => 'InNegotiation',
        self::EXPIRED => 'Expired',
        self::PENDING => 'Pending',
    ];

    private function __construct()
    {
    }

    public static function fromWireValue(int $value): int
    {
        return EnumLookup::fromValue(self::NAMES, $value);
    }

    /**
     * Aceita o nome em português (`APROVADA`) e em inglês (`Approved`).
     */
    public static function fromWireName(?string $name): int
    {
        return EnumLookup::fromName($name, self::NAMES, self::ENGLISH_NAMES);
    }

    public static function wireName(int $value): ?string
    {
        return EnumLookup::name(self::NAMES, $value);
    }
}
