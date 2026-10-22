<?php

declare(strict_types=1);

namespace Twila\Credicarro\Customers;

use Twila\Credicarro\Internal\Mapping\EnumLookup;

/**
 * Tipo de ocupação do cliente (`tipoOcupacao`). O código numérico é o mesmo da API (não existe o 5).
 */
final class OccupationType
{
    public const RETIRED = 1;
    public const PENSIONER = 2;
    public const EMPLOYEE = 3;
    public const SELF_EMPLOYED = 4;
    public const FREELANCER = 6;
    public const BUSINESS_OWNER = 7;
    public const UNKNOWN = -1;

    private const NAMES = [
        self::RETIRED => 'Aposentado',
        self::PENSIONER => 'Pensionista',
        self::EMPLOYEE => 'Assalariado',
        self::SELF_EMPLOYED => 'Autônomo',
        self::FREELANCER => 'Freelancer',
        self::BUSINESS_OWNER => 'Proprietário',
    ];

    private function __construct()
    {
    }

    public static function fromWireValue(int $value): int
    {
        return EnumLookup::fromValue(self::NAMES, $value);
    }
}
