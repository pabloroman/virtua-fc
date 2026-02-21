<?php

namespace App\Modules\Season\Processors;

use App\Modules\Competition\Services\StandingsCalculator;
use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Services\SeasonInitializationService;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;

/**
 * Initializes the player's primary competition (domestic league):
 * generates fixtures and creates/resets standings.
 *
 * Priority: 30 (runs after SeasonDataCleanupProcessor at 10)
 */
class PrimaryCompetitionInitProcessor implements SeasonEndProcessor
{
    public function __construct(
        private readonly SeasonInitializationService $service,
        private readonly StandingsCalculator $standingsCalculator,
    ) {}

    public function priority(): int
    {
        return 30;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Generate league fixtures
        $this->service->generateLeagueFixtures($game->id, $data->competitionId, $data->newSeason);

        // Initialize or reset standings
        if ($data->isInitialSeason) {
            $this->createInitialStandings($game, $data);
        } else {
            $this->resetExistingStandings($game, $data);
        }

        return $data;
    }

    private function createInitialStandings(Game $game, SeasonTransitionData $data): void
    {
        $teamIds = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $data->competitionId)
            ->join('teams', 'teams.id', '=', 'competition_entries.team_id')
            ->orderBy('teams.name')
            ->pluck('competition_entries.team_id')
            ->toArray();

        $this->standingsCalculator->initializeStandings($game->id, $data->competitionId, $teamIds);
    }

    private function resetExistingStandings(Game $game, SeasonTransitionData $data): void
    {
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

        // Reset all standings - keep positions from last season
        foreach ($finalStandings as $standing) {
            $standing->update([
                'prev_position' => null,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
            ]);
        }
    }
}
