<?php

namespace App\Modules\Notification\Listeners;

use App\Models\GameNotification;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Transfer\Enums\TransferWindowType;

class NotifyTransferWindowClosed
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        // Detect if the date jumped out of a window: previousDate was inside,
        // newDate is outside. This means the window closed between the two matchdays.
        $previousWindow = TransferWindowType::fromDate($event->previousDate);
        $newWindow = TransferWindowType::fromDate($event->newDate);

        if (! $previousWindow || $newWindow !== null) {
            return;
        }

        $game = $event->game;

        $alreadyNotified = GameNotification::where('game_id', $game->id)
            ->where('type', GameNotification::TYPE_TRANSFER_WINDOW_CLOSED)
            ->whereJsonContains('metadata->window', $previousWindow->value)
            ->where('game_date', '>=', $event->previousDate->copy()->startOfMonth())
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        $this->notificationService->notifyTransferWindowClosed($game, $previousWindow->value);
    }
}
