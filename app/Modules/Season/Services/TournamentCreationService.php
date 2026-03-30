<?php

namespace App\Modules\Season\Services;

use App\Modules\Lineup\Enums\Formation;
use App\Modules\Season\Jobs\SetupTournamentGame;
use App\Models\Game;
use App\Models\GameTactics;
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
            'country' => $team->fifa_code ?? 'XXX',
            'team_id' => $teamId,
            'competition_id' => 'WC2026',
            'season' => '2025',
            'current_date' => '2026-06-11',
            'current_matchday' => 0,
            'needs_welcome' => true,
            'needs_new_season_setup' => true,
            'setup_completed_at' => null,
        ]);

        // Create default tactical settings
        GameTactics::create(['game_id' => $gameId, 'default_formation' => Formation::F_4_3_3->value]);

        SetupTournamentGame::dispatch(
            gameId: $gameId,
            teamId: $teamId,
        );

        return $game;
    }
}
