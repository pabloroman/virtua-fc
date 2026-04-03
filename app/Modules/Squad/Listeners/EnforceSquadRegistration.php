<?php

namespace App\Modules\Squad\Listeners;

use App\Models\GamePlayer;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Transfer\Enums\TransferWindowType;

class EnforceSquadRegistration
{
    public function handle(GameDateAdvanced $event): void
    {
        // Only detect transfer window close boundary:
        // previousDate was inside a window, newDate is outside.
        $previousWindow = TransferWindowType::fromDate($event->previousDate);
        $newWindow = TransferWindowType::fromDate($event->newDate);

        if (! $previousWindow || $newWindow !== null) {
            return;
        }

        $game = $event->game;

        if (! $game->squad_registration_enabled) {
            return;
        }

        $hasUnregistered = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereNull('number')
            ->exists();

        if ($hasUnregistered) {
            $game->addPendingAction('squad_registration', 'game.squad.registration');
        }
    }
}
