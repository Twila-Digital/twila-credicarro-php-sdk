<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

use Twila\Credicarro\Internal\Mapping\EnumLookup;

/**
 * Etapa da proposta (`enum_etapa` / `etapa`).
 */
final class ProposalStep
{
    public const UNDEFINED = 0;
    public const PROPOSAL_CREATED = 1;
    public const PROPOSAL_APPROVED = 2;
    public const SELLER_NOTIFICATION = 3;
    public const BIOMETRY_ANALYSIS = 4;
    public const DEPOSIT_ACCOUNT = 5;
    public const DOCUMENT_SUBMISSION = 6;
    public const DOCUMENT_CORRECTION = 7;
    public const VEHICLE_DETAILS = 8;
    public const INSPECTION = 9;
    public const SIGNATURE = 10;
    public const PAYMENT = 11;
    public const PROPOSAL_COMPLETED = 12;
    public const UNKNOWN = -1;

    private const NAMES = [
        self::UNDEFINED => 'INDEFINIDO',
        self::PROPOSAL_CREATED => 'PROPOSTA_CRIADA',
        self::PROPOSAL_APPROVED => 'PROPOSTA_APROVADA',
        self::SELLER_NOTIFICATION => 'NOTIFICACAO_VENDEDOR',
        self::BIOMETRY_ANALYSIS => 'ANALISE_BIOMETRIA',
        self::DEPOSIT_ACCOUNT => 'CONTA_RECEBIMENTO',
        self::DOCUMENT_SUBMISSION => 'ENVIO_DOCUMENTOS',
        self::DOCUMENT_CORRECTION => 'CORRECAO_DOCUMENTOS',
        self::VEHICLE_DETAILS => 'DADOS_VEICULO',
        self::INSPECTION => 'VISTORIA',
        self::SIGNATURE => 'ASSINATURA',
        self::PAYMENT => 'PAGAMENTO',
        self::PROPOSAL_COMPLETED => 'PROPOSTA_FINALIZADA',
    ];

    private function __construct()
    {
    }

    public static function fromWireValue(int $value): int
    {
        return EnumLookup::fromValue(self::NAMES, $value);
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
