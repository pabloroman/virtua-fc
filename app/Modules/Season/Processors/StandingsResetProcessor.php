<?php

namespace App\Modules\Season\Processors;

use App\Modules\Competition\Services\StandingsCalculator;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;

/**
 * Resets league standings for the new season (or creates them for initial season).
 * Preserves team positions from previous season for initial ordering.
 * Priority: 40 (runs last)
 */
class StandingsResetProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly StandingsCalculator $standingsCalculator,
    ) {}

    public function priority(): int
    {
        return 40;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        if ($data->isInitialSeason) {
            return $this->createInitialStandings($game, $data);
        }

        return $this->resetExistingStandings($game, $data);
    }

    private function createInitialStandings(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $teamIds = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $data->competitionId)
            ->pluck('team_id')
            ->toArray();

        $this->standingsCalculator->initializeStandings($game->id, $data->competitionId, $teamIds);

        return $data;
    }

    private function resetExistingStandings(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // If the player's league has no standings (promoted to a previously-simulated league),
        // create initial standings instead of resetting.
        $hasStandings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $data->competitionId)
            ->exists();

        if (!$hasStandings) {
            $this->cleanupStaleLeagueStandings($game, $data);

            return $this->createInitialStandings($game, $data);
        }

        // Store final positions for metadata
        $finalStandings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $data->competitionId)
            ->orderBy('position')
            ->get();

        $data->setMetadata('finalStandings', $finalStandings->map(fn ($s) => [
            'position' => $s->position,
            'teamId' => $s->team_id,
            'points' => $s->points,
            'goalDifference' => $s->goal_difference,
        ])->toArray());

        // Reset all standings in a single query - keep positions from last season
        GameStanding::where('game_id', $game->id)
            ->where('competition_id', $data->competitionId)
            ->update([
                'prev_position' => null,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
                'form' => null,
            ]);

        // Clean up stale standings from non-player leagues (old division data, bootstrapped zeros)
        $this->cleanupStaleLeagueStandings($game, $data);

        return $data;
    }

    /**
     * Delete standings for non-player leagues.
     * These are either stale previous-season data or bootstrapped zeros from promotion.
     */
    private function cleanupStaleLeagueStandings(Game $game, SeasonTransitionData $data): void
    {
        $leagueCompetitionIds = Competition::where('role', Competition::ROLE_LEAGUE)
            ->pluck('id');

        GameStanding::where('game_id', $game->id)
            ->where('competition_id', '!=', $data->competitionId)
            ->whereIn('competition_id', $leagueCompetitionIds)
            ->delete();
    }
}
