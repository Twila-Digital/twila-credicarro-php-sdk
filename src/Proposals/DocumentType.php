<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

use Twila\Credicarro\Internal\Mapping\EnumLookup;

/**
 * Tipo de documento (`tipoDocumento` no envio; `enum_tipo` / `tipo` na consulta). O código numérico é o mesmo da API.
 *
 * Aceitos por participante no envio: COMPRADOR — 1, 4, 5, 6, 7, 9, 57; VENDEDOR — 1, 2, 3, 5, 6, 7, 8, 9, 11;
 * LOJA — 6, 8.
 */
final class DocumentType
{
    public const CNH = 1;
    public const CPF = 2;
    public const RG = 3;
    public const PROOF_OF_INCOME = 4;
    public const PROOF_OF_ADDRESS = 5;
    public const VEHICLE_DOCUMENT = 6;
    public const DIGITAL_CNH = 7;
    public const ECV_REPORT = 8;
    public const INSPECTION_REPORT = 9;
    public const CNPJ_CARD = 11;
    public const ATPV = 57;
    public const UNKNOWN = -1;

    private const NAMES = [
        self::CNH => 'CNH',
        self::CPF => 'CPF',
        self::RG => 'RG',
        self::PROOF_OF_INCOME => 'COMPROVANTE_RENDA',
        self::PROOF_OF_ADDRESS => 'COMPROVANTE_RESIDENCIA',
        self::VEHICLE_DOCUMENT => 'DOCUMENTO_VEICULO',
        self::DIGITAL_CNH => 'CNH_DIGITAL',
        self::ECV_REPORT => 'LAUDO_ECV',
        self::INSPECTION_REPORT => 'LAUDO_VISTORIA',
        self::CNPJ_CARD => 'CARTAO_CNPJ',
        self::ATPV => 'ATPV',
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
