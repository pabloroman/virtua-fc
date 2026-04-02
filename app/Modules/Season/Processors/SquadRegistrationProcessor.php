<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Squad\Services\SquadRegistrationService;

class SquadRegistrationProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly SquadRegistrationService $registrationService,
    ) {}

    public function priority(): int
    {
        return 109; // After ContinentalAndCupInit (106), before NewSeasonReset (110)
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Auto-register all AI teams
        $aiTeamIds = Team::where('id', '!=', $game->team_id)
            ->whereHas('gamePlayers', fn ($q) => $q->where('game_id', $game->id))
            ->pluck('id');

        foreach ($aiTeamIds as $teamId) {
            $this->registrationService->autoAssignRegistration($game, $teamId);
        }

        // For the user's team: skip registration in the first season (auto-register everyone)
        if ($data->isInitialSeason) {
            $this->registrationService->autoAssignRegistration($game);
            return $data;
        }

        // Season 2+: clear user's numbers and require manual registration
        GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->update(['number' => null]);

        $game->addPendingAction('squad_registration', 'game.squad.registration');

        return $data;
    }
}
