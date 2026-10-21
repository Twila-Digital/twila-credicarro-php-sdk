<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

/**
 * Ação que o integrador precisa executar (resultado de `ProposalDetails::getPendingActions()`).
 */
final class PendingActionKind
{
    public const SEND_DOCUMENTS = 'SendDocuments';
    public const CORRECT_DOCUMENTS = 'CorrectDocuments';
    public const SEND_VEHICLE_DETAILS = 'SendVehicleDetails';
    public const SEND_DEPOSIT_ACCOUNT = 'SendDepositAccount';

    private function __construct()
    {
    }
}
