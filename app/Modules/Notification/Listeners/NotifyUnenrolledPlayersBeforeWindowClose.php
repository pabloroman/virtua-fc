<?php

namespace App\Modules\Notification\Listeners;

use App\Models\GameMatch;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Transfer\Enums\TransferWindowType;

class NotifyUnenrolledPlayersBeforeWindowClose
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        $game = $event->game;

        if (! $game->squad_registration_enabled) {
            return;
        }

        // current_date is forward-looking: newDate is the upcoming match.
        $windowType = TransferWindowType::fromDate($event->newDate);

        if (! $windowType) {
            return;
        }

        // Only fire on the last matchday before the window closes — same
        // detection used by NotifyTransferWindowClosing.
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

        $unenrolledCount = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereNull('number')
            ->count();

        if ($unenrolledCount === 0) {
            return;
        }

        $alreadyNotified = GameNotification::where('game_id', $game->id)
            ->where('type', GameNotification::TYPE_SQUAD_REGISTRATION_REQUIRED)
            ->whereJsonContains('metadata->window', $windowType->value)
            ->where('game_date', '>=', $event->newDate->copy()->startOfMonth())
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        $this->notificationService->notifyUnenrolledBeforeWindowClose(
            $game,
            $unenrolledCount,
            $windowType->value,
        );
    }
}
