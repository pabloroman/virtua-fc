<?php

namespace App\Modules\Notification\Listeners;

use App\Models\GameMatch;
use App\Models\GameNotification;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Notification\Services\NotificationService;

class NotifyTransferWindowClosing
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        $game = $event->game;

        if (! $game->isTransferWindowOpen()) {
            return;
        }

        // Check if the next match after current_date falls outside the window
        $nextMatch = GameMatch::where('game_id', $game->id)
            ->where('played', false)
            ->where('scheduled_date', '>', $event->newDate->toDateString())
            ->orderBy('scheduled_date')
            ->first();

        if (! $nextMatch) {
            return;
        }

        $nextMonth = $nextMatch->scheduled_date->month;
        $isLastMatchday = $game->isSummerWindowOpen()
            ? ! in_array($nextMonth, [7, 8])
            : $nextMonth !== 1;

        if (! $isLastMatchday) {
            return;
        }

        // Avoid duplicate notifications
        $alreadyNotified = GameNotification::where('game_id', $game->id)
            ->where('type', GameNotification::TYPE_TRANSFER_WINDOW_CLOSING)
            ->where('game_date', $game->current_date)
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        $window = $game->isSummerWindowOpen() ? 'summer' : 'winter';
        $this->notificationService->notifyTransferWindowClosing($game, $window);
    }
}
