<?php

namespace App\Modules\Transfer\Listeners;

use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Transfer\Enums\TransferWindowType;
use App\Modules\Transfer\Services\TransferService;

/**
 * Completes agreed transfers once the team has played a match in the window.
 *
 * Transfers accepted while a window is OPEN are no longer completed
 * immediately — they sit as STATUS_AGREED with resolved_at = current_date
 * (i.e. the upcoming match the player would otherwise have plugged) and wait
 * for that match to be played. After the match finalises, current_date moves
 * forward and this listener flushes the agreements so the player joins from
 * the *following* matchday onwards.
 *
 * The guard "previousDate was inside a transfer window" deliberately covers
 * both the intra-window match case and the window-close boundary (where a
 * summer signing made during pre-season needs to join before the league
 * starts). The closed→open boundary stays the responsibility of
 * CompleteAgreedTransfersOnWindowOpen.
 */
class CompleteAgreedTransfersOnMatchPlayed
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        if (TransferWindowType::fromDate($event->previousDate) === null) {
            return;
        }

        $game = $event->game;

        $completedOutgoing = $this->transferService->completeAgreedTransfers($game);
        $completedIncoming = $this->transferService->completeIncomingTransfers($game);

        foreach ($completedOutgoing->merge($completedIncoming) as $offer) {
            $this->notificationService->notifyTransferComplete($game, $offer);
        }
    }
}
