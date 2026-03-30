<?php

namespace App\Modules\Notification\Listeners;

use App\Models\GameNotification;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Notification\Services\NotificationService;

class NotifyTransferWindowOpen
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        $previousMonth = $event->previousDate->month;
        $newMonth = $event->newDate->month;

        // Detect if the date jumped into a window that wasn't open before.
        // Summer window open is handled at season start — only detect winter here.
        $winterOpened = $previousMonth !== 1 && $newMonth === 1;

        if (! $winterOpened) {
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

        $notification = $this->notificationService->notifyTransferWindowOpen($game, 'winter');

        // Backdate to the actual window start (Jan 1) rather than the matchday
        // the system detected it, since the date may have jumped past the start.
        $windowStart = $event->newDate->copy()->startOfMonth();
        $notification->update(['game_date' => $windowStart]);
    }
}
