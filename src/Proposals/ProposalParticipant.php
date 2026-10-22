<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

/**
 * Participante dono do documento (`participante` no envio de documento). O valor é o texto da API.
 */
final class ProposalParticipant
{
    public const BUYER = 'COMPRADOR';
    public const SELLER = 'VENDEDOR';
    public const STORE = 'LOJA';

    private function __construct()
    {
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [self::BUYER, self::SELLER, self::STORE];
    }
}
