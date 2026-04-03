<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;

class SquadRegistrationEnforcementProcessor implements SeasonProcessor
{
    public function priority(): int
    {
        return 109;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Enable squad registration for existing games starting a new season.
        // New games already have this set to true at creation.
        if (! $game->squad_registration_enabled) {
            $game->update(['squad_registration_enabled' => true]);
        }

        $hasUnregistered = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereNull('number')
            ->exists();

        if ($hasUnregistered) {
            $game->addPendingAction('squad_registration', 'game.squad.registration');
        }

        return $data;
    }
}
