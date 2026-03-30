<?php

namespace App\Modules\Notification\Listeners;

use App\Models\GameNotification;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Transfer\Enums\TransferWindowType;

class NotifyTransferWindowOpen
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        // Summer window open is handled at season start — only detect winter here.
        $newWindow = TransferWindowType::fromDate($event->newDate);
        $previousWindow = TransferWindowType::fromDate($event->previousDate);

        if ($newWindow !== TransferWindowType::WINTER || $previousWindow === TransferWindowType::WINTER) {
            return;
        }

        $game = $event->game;

        $alreadyNotified = GameNotification::where('game_id', $game->id)
            ->where('type', GameNotification::TYPE_TRANSFER_WINDOW_OPEN)
            ->where('game_date', '>=', $event->newDate->copy()->startOfMonth())
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        $notification = $this->notificationService->notifyTransferWindowOpen($game, TransferWindowType::WINTER->value);

        // Backdate to the actual window start (Jan 1) rather than the matchday
        // the system detected it, since the date may have jumped past the start.
        $windowStart = $event->newDate->copy()->startOfMonth();
        $notification->update(['game_date' => $windowStart]);
    }
}
