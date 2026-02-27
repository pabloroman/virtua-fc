<?php

namespace App\Modules\Season\Services;

use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Season\Jobs\SetupNewGame;
use App\Models\Competition;
use App\Models\CompetitionTeam;
use App\Models\Game;
use App\Models\GameTactics;
use App\Models\Team;
use Ramsey\Uuid\Uuid;

class GameCreationService
{
    public function create(string $userId, string $teamId, string $gameMode = 'career'): Game
    {
        $gameId = Uuid::uuid4()->toString();

        // Find competition for the selected team (prefer primary league, then any)
        $competitionTeam = CompetitionTeam::where('team_id', $teamId)
            ->whereHas('competition', fn($q) => $q->where('role', Competition::ROLE_LEAGUE)->where('tier', 1))
            ->first()
            ?? CompetitionTeam::where('team_id', $teamId)
                ->whereHas('competition', fn($q) => $q->where('role', Competition::ROLE_PRIMARY))
                ->first()
            ?? CompetitionTeam::where('team_id', $teamId)->first();

        // Resolve competition ID: use competition_team lookup, fall back to
        // tier 1 of the team's country from config
        $competitionId = $competitionTeam?->competition_id;
        if (!$competitionId) {
            $team = Team::find($teamId);
            $countryConfig = app(CountryConfig::class);
            $competitionId = $countryConfig->competitionForTier($team->country ?? 'ES', 1);
        }
        $season = $competitionTeam->season ?? '2025';

        $team = Team::find($teamId);

        // Create game record (setup not yet complete)
        // current_date and season_goal are set by processors during SetupNewGame
        $game = Game::create([
            'id' => $gameId,
            'user_id' => $userId,
            'game_mode' => $gameMode,
            'country' => $team->country ?? 'ES',
            'team_id' => $teamId,
            'competition_id' => $competitionId,
            'season' => $season,
            'current_date' => null,
            'current_matchday' => 0,
            'season_goal' => null,
            'setup_completed_at' => null,
        ]);

        // Create default tactical settings
        GameTactics::create(['game_id' => $gameId]);

        // Dispatch heavy initialization to a queued job
        SetupNewGame::dispatch(
            gameId: $gameId,
            teamId: $teamId,
            competitionId: $competitionId,
            season: $season,
            gameMode: $gameMode,
        );

        return $game;
    }
}
