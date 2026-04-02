<?php

namespace App\Modules\Squad\Listeners;

use App\Models\GamePlayer;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Transfer\Enums\TransferWindowType;

class TriggerSquadReRegistration
{
    public function handle(GameDateAdvanced $event): void
    {
        $game = $event->game;

        if (!$game->isCareerMode()) {
            return;
        }

        // Detect transfer window closing: previousDate was inside a window, newDate is outside
        $previousWindow = TransferWindowType::fromDate($event->previousDate);
        $newWindow = TransferWindowType::fromDate($event->newDate);

        if (!$previousWindow || $newWindow !== null) {
            return;
        }

        // Only trigger if the user has unregistered players (registration swap opportunity)
        $hasUnregistered = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereNull('number')
            ->exists();

        if ($hasUnregistered) {
            $game->addPendingAction('squad_registration', 'game.squad.registration');
        }
    }
}
