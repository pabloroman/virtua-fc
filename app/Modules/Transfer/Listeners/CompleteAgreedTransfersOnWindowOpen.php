<?php

namespace App\Modules\Transfer\Listeners;

use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Transfer\Enums\TransferWindowType;
use App\Modules\Transfer\Services\TransferService;

/**
 * Completes agreed transfers the moment the transfer window opens.
 *
 * Transfers accepted while the window is closed are marked STATUS_AGREED and
 * sit waiting until the window opens. Without this listener, they are only
 * flushed by CareerActionProcessor on the next matchday played *inside* the
 * window — which, combined with the forward-looking current_date, can leave a
 * user-visible ~1 week gap between finalising the last match before the window
 * and actually playing the first match in the window. This listener closes
 * that gap by completing agreed transfers in the same finalization flow that
 * advances current_date into the window.
 */
class CompleteAgreedTransfersOnWindowOpen
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        // Detect boundary crossing: previousDate was outside a window, newDate is inside one.
        $previousWindow = TransferWindowType::fromDate($event->previousDate);
        $newWindow = TransferWindowType::fromDate($event->newDate);

        if ($previousWindow !== null || $newWindow === null) {
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
