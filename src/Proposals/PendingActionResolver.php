<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

/**
 * @internal
 */
final class PendingActionResolver
{
    /**
     * @return array<int, PendingAction>
     */
    public static function resolve(
        ?ProposalParticipantState $buyer,
        ?ProposalParticipantState $seller,
        ?ProposalParticipantState $store
    ): array {
        $participants = [
            ProposalParticipant::BUYER => $buyer,
            ProposalParticipant::SELLER => $seller,
            ProposalParticipant::STORE => $store,
        ];

        $actions = [];
        foreach ($participants as $participant => $state) {
            $action = self::actionFor($participant, $state);
            if ($action !== null) {
                $actions[] = $action;
            }
        }

        return $actions;
    }

    private static function actionFor(string $participant, ?ProposalParticipantState $state): ?PendingAction
    {
        if ($state === null || $state->activeStep === null) {
            return null;
        }

        $active = $state->activeStep;
        if ($active->actionOwner !== ActionOwner::INTEGRATOR) {
            return null;
        }

        switch ($active->step) {
            case ProposalStep::DOCUMENT_SUBMISSION:
                return new PendingAction(
                    $participant,
                    $active->step,
                    PendingActionKind::SEND_DOCUMENTS,
                    self::documentTypes($state, DocumentStatus::PENDING)
                );
            case ProposalStep::DOCUMENT_CORRECTION:
                return new PendingAction(
                    $participant,
                    $active->step,
                    PendingActionKind::CORRECT_DOCUMENTS,
                    self::documentTypes($state, DocumentStatus::INVALID)
                );
            case ProposalStep::VEHICLE_DETAILS:
                return new PendingAction($participant, $active->step, PendingActionKind::SEND_VEHICLE_DETAILS);
            case ProposalStep::DEPOSIT_ACCOUNT:
                return new PendingAction($participant, $active->step, PendingActionKind::SEND_DEPOSIT_ACCOUNT);
            default:
                return null;
        }
    }

    /**
     * @return array<int, int>
     */
    private static function documentTypes(ProposalParticipantState $state, int $status): array
    {
        $types = [];
        foreach ($state->documents as $document) {
            if ($document->status === $status && $document->typeRaw !== null) {
                $types[] = $document->typeRaw;
            }
        }

        return $types;
    }
}
