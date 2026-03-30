<?php

namespace App\Modules\Notification\Listeners;

use App\Models\GameMatch;
use App\Models\GameNotification;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Transfer\Enums\TransferWindowType;

class NotifyTransferWindowClosing
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        // current_date is forward-looking: newDate is the upcoming match.
        $windowType = TransferWindowType::fromDate($event->newDate);

        if (! $windowType) {
            return;
        }

        $game = $event->game;

        // If the match after the upcoming one is outside the window,
        // then the upcoming match is the last one before the window closes.
        $nextMatch = GameMatch::where('game_id', $game->id)
            ->where('played', false)
            ->where('scheduled_date', '>', $event->newDate)
            ->orderBy('scheduled_date')
            ->first();

        if (! $nextMatch) {
            return;
        }

        if ($windowType->containsMonth($nextMatch->scheduled_date->month)) {
            return;
        }

        $alreadyNotified = GameNotification::where('game_id', $game->id)
            ->where('type', GameNotification::TYPE_TRANSFER_WINDOW_CLOSING)
            ->whereJsonContains('metadata->window', $windowType->value)
            ->where('game_date', '>=', $event->newDate->copy()->startOfMonth())
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        $this->notificationService->notifyTransferWindowClosing($game, $windowType->value);
    }
}
