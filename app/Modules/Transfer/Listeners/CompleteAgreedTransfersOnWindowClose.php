<?php

namespace App\Modules\Transfer\Listeners;

use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Transfer\Enums\TransferWindowType;
use App\Modules\Transfer\Services\TransferService;

/**
 * When the game date jumps from inside a transfer window to outside it
 * (e.g. the last January match advances current_date to February),
 * complete any agreed transfers before the window is considered closed.
 *
 * This prevents the forward-looking current_date from causing
 * CareerActionProcessor to skip agreed transfer completion because
 * it sees the post-window date.
 */
class CompleteAgreedTransfersOnWindowClose
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        $previousWindow = TransferWindowType::fromDate($event->previousDate);
        $newWindow = TransferWindowType::fromDate($event->newDate);

        // Only act when crossing from inside a window to outside it
        if (! $previousWindow || $newWindow !== null) {
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
