<?php

namespace App\Modules\Notification\Listeners;

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
        $previousMonth = $event->previousDate->month;
        $newMonth = $event->newDate->month;

        // Check if the new date (the upcoming match) is inside a transfer window.
        // current_date is always the date of the next match to be played, so
        // newDate is the upcoming match the user is about to play.
        $window = match (true) {
            in_array($newMonth, [7, 8]) => 'summer',
            $newMonth === 1 => 'winter',
            default => null,
        };

        if (! $window) {
            return;
        }

        $game = $event->game;

        // Check if the next unplayed match after newDate falls outside the window.
        // If so, newDate's match is the last one before the window closes.
        $nextMatch = \App\Models\GameMatch::where('game_id', $game->id)
            ->where('played', false)
            ->where('scheduled_date', '>', $event->newDate->toDateString())
            ->orderBy('scheduled_date')
            ->first();

        if (! $nextMatch) {
            return;
        }

        $nextMonth = $nextMatch->scheduled_date->month;
        $isLastMatchday = $window === 'summer'
            ? ! in_array($nextMonth, [7, 8])
            : $nextMonth !== 1;

        if (! $isLastMatchday) {
            return;
        }

        $alreadyNotified = GameNotification::where('game_id', $game->id)
            ->where('type', GameNotification::TYPE_TRANSFER_WINDOW_CLOSING)
            ->whereJsonContains('metadata->window', $window)
            ->where('game_date', '>=', $event->previousDate->copy()->startOfMonth())
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        $this->notificationService->notifyTransferWindowClosing($game, $window);
    }
}
