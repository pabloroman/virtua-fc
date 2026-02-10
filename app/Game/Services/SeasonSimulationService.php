<?php

namespace App\Game\Services;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\SimulatedSeason;
use App\Models\Team;

class SeasonSimulationService
{
    public function __construct(
        private readonly BudgetProjectionService $budgetService,
    ) {}

    /**
     * Simulate a league season for a non-played competition.
     * Uses squad strengths with bounded random noise to produce realistic standings.
     */
    public function simulateLeague(Game $game, Competition $competition): SimulatedSeason
    {
        $teamIds = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->pluck('team_id');

        $teams = Team::whereIn('id', $teamIds)->get();

        // Calculate strength with random noise for each team
        $rankings = [];
        foreach ($teams as $team) {
            $strength = $this->budgetService->calculateSquadStrength($game, $team);
            $noise = mt_rand(-40, 40) / 10; // ±4.0
            $rankings[] = [
                'team_id' => $team->id,
                'score' => $strength + $noise,
            ];
        }

        // Sort by noisy strength descending
        usort($rankings, fn ($a, $b) => $b['score'] <=> $a['score']);

        $results = array_column($rankings, 'team_id');

        return SimulatedSeason::updateOrCreate(
            [
                'game_id' => $game->id,
                'season' => $game->season,
                'competition_id' => $competition->id,
            ],
            [
                'results' => $results,
            ]
        );
    }
}
