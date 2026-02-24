<?php

namespace App\Modules\Season\Services;

use App\Modules\Season\Jobs\SetupTournamentGame;
use App\Models\Game;
use App\Models\Team;
use Ramsey\Uuid\Uuid;

class TournamentCreationService
{
    public function create(string $userId, string $teamId): Game
    {
        $gameId = Uuid::uuid4()->toString();

        $team = Team::findOrFail($teamId);

        $game = Game::create([
            'id' => $gameId,
            'user_id' => $userId,
            'game_mode' => Game::MODE_TOURNAMENT,
            'country' => $team->country ?? 'INT',
            'team_id' => $teamId,
            'competition_id' => 'WC2026',
            'season' => '2025',
            'current_date' => '2026-06-11',
            'current_matchday' => 0,
            'needs_welcome' => false,
            'needs_onboarding' => true,
            'setup_completed_at' => null,
        ]);

        SetupTournamentGame::dispatch(
            gameId: $gameId,
            teamId: $teamId,
        );

        return $game;
    }
}
