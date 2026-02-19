<?php

namespace App\Modules\Season\Services;

use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Season\Jobs\SetupNewGame;
use App\Models\Competition;
use App\Models\CompetitionTeam;
use App\Models\Game;
use App\Models\Team;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use App\Modules\Competition\Services\LeagueFixtureGenerator;
use App\Modules\Season\Services\SeasonGoalService;

class GameCreationService
{
    public function __construct(
        private readonly SeasonGoalService $seasonGoalService,
    ) {}

    public function create(string $userId, string $playerName, string $teamId, string $gameMode = 'career'): Game
    {
        $gameId = Uuid::uuid4()->toString();

        // Find competition for the selected team (prefer tier 1 league)
        $competitionTeam = CompetitionTeam::where('team_id', $teamId)
            ->whereHas('competition', fn($q) => $q->where('role', Competition::ROLE_PRIMARY)->where('tier', 1))
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

        // Load matchday calendar for initial current_date
        $matchdays = LeagueFixtureGenerator::loadMatchdays($competitionId, $season);
        $firstDate = Carbon::parse($matchdays[0]['date']);

        // Determine initial season goal based on team reputation
        $team = Team::with('clubProfile')->find($teamId);
        $competition = Competition::find($competitionId);
        $seasonGoal = $this->seasonGoalService->determineGoalForTeam($team, $competition);

        // Create game record (setup not yet complete)
        $game = Game::create([
            'id' => $gameId,
            'user_id' => $userId,
            'game_mode' => $gameMode,
            'country' => $team->country ?? 'ES',
            'player_name' => $playerName,
            'team_id' => $teamId,
            'competition_id' => $competitionId,
            'season' => $season,
            'current_date' => $firstDate->toDateString(),
            'current_matchday' => 0,
            'season_goal' => $seasonGoal,
            'setup_completed_at' => null,
        ]);

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
