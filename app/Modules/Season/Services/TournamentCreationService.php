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
    private const SEASON = '2025';

    /** Fallback start date used when a tournament ships no league schedule (WC2026 groups). */
    private const DEFAULT_START_DATE = '2026-06-11';

    public function create(string $userId, string $teamId, string $competitionId = 'WC2026'): Game
    {
        $gameId = Uuid::uuid4()->toString();

        $team = Team::findOrFail($teamId);

        $game = Game::create([
            'id' => $gameId,
            'user_id' => $userId,
            'game_mode' => Game::MODE_TOURNAMENT,
            'country' => $team->fifa_code ?? 'XXX',
            'team_id' => $teamId,
            'competition_id' => $competitionId,
            'season' => self::SEASON,
            'current_date' => $this->resolveStartDate($competitionId),
            'needs_welcome' => true,
            'needs_new_season_setup' => true,
            'setup_completed_at' => null,
        ]);

        // Create default tactical settings
        GameTactics::create(['game_id' => $gameId, 'default_formation' => Formation::F_4_3_3->value]);

        SetupTournamentGame::dispatch(
            gameId: $gameId,
            teamId: $teamId,
            competitionId: $competitionId,
        );

        return $game;
    }

    /**
     * The Swiss-format tournament opens on its first league matchday (read from
     * schedule.json); the group-stage World Cup ships no league block, so it
     * falls back to the fixed group-stage kickoff date.
     */
    private function resolveStartDate(string $competitionId): string
    {
        $path = base_path('data/' . self::SEASON . "/{$competitionId}/schedule.json");

        if (is_file($path)) {
            $schedule = json_decode(file_get_contents($path), true);
            $firstLeagueDate = $schedule['league'][0]['date'] ?? null;

            if ($firstLeagueDate) {
                return $firstLeagueDate;
            }
        }

        return self::DEFAULT_START_DATE;
    }
}
