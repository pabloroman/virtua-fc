<?php

namespace App\Modules\Finance\Services;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\SimulatedSeason;
use App\Models\Team;
use App\Modules\Finance\Services\BudgetProjectionService;

class SeasonSimulationService
{
    public function __construct(
        private readonly BudgetProjectionService $budgetService,
    ) {}

    /**
     * Simulate a league season for a non-played competition.
     * Runs a full match-by-match simulation using Poisson-distributed goals
     * and the same ratio-based xG formula as MatchSimulator.
     */
    public function simulateLeague(Game $game, Competition $competition): SimulatedSeason
    {
        $teamIds = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->pluck('team_id');

        $teams = Team::whereIn('id', $teamIds)->get();

        // Calculate squad strength for each team (0-100 scale)
        $strengths = [];
        foreach ($teams as $team) {
            $strengths[$team->id] = $this->budgetService->calculateSquadStrength($game, $team);
        }

        // Initialize standings
        $standings = [];
        foreach ($teams as $team) {
            $standings[$team->id] = [
                'points' => 0,
                'gf' => 0,
                'ga' => 0,
            ];
        }

        // Simulate every home/away fixture (N × (N-1) matches)
        $teamList = $teams->values();
        $baseGoals = config('match_simulation.base_goals', 1.3);
        $ratioExponent = config('match_simulation.ratio_exponent', 2.0);
        $homeAdvantageGoals = config('match_simulation.home_advantage_goals', 0.15);

        for ($i = 0; $i < $teamList->count(); $i++) {
            for ($j = 0; $j < $teamList->count(); $j++) {
                if ($i === $j) {
                    continue;
                }

                $homeId = $teamList[$i]->id;
                $awayId = $teamList[$j]->id;

                // Normalize strengths to 0-1 range (same as MatchSimulator)
                $homeStrength = $strengths[$homeId] / 100;
                $awayStrength = $strengths[$awayId] / 100;

                $result = $this->simulateMatchResult(
                    $homeStrength, $awayStrength,
                    $baseGoals, $ratioExponent, $homeAdvantageGoals
                );

                $homeGoals = $result[0];
                $awayGoals = $result[1];

                $standings[$homeId]['gf'] += $homeGoals;
                $standings[$homeId]['ga'] += $awayGoals;
                $standings[$awayId]['gf'] += $awayGoals;
                $standings[$awayId]['ga'] += $homeGoals;

                if ($homeGoals > $awayGoals) {
                    $standings[$homeId]['points'] += 3;
                } elseif ($homeGoals === $awayGoals) {
                    $standings[$homeId]['points'] += 1;
                    $standings[$awayId]['points'] += 1;
                } else {
                    $standings[$awayId]['points'] += 3;
                }
            }
        }

        // Sort by points → goal difference → goals for
        uasort($standings, function ($a, $b) {
            $pointsDiff = $b['points'] <=> $a['points'];
            if ($pointsDiff !== 0) {
                return $pointsDiff;
            }

            $gdDiff = ($b['gf'] - $b['ga']) <=> ($a['gf'] - $a['ga']);
            if ($gdDiff !== 0) {
                return $gdDiff;
            }

            return $b['gf'] <=> $a['gf'];
        });

        $results = array_keys($standings);

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

    /**
     * Simulate a single match using ratio-based xG and Poisson goals.
     *
     * @param  float  $homeStrength  Home team strength (0-1 scale)
     * @param  float  $awayStrength  Away team strength (0-1 scale)
     * @return array{0: int, 1: int} [homeGoals, awayGoals]
     */
    private function simulateMatchResult(
        float $homeStrength,
        float $awayStrength,
        float $baseGoals,
        float $ratioExponent,
        float $homeAdvantageGoals,
    ): array {
        $strengthRatio = $awayStrength > 0 ? $homeStrength / $awayStrength : 1.0;

        $homeXG = pow($strengthRatio, $ratioExponent) * $baseGoals + $homeAdvantageGoals;
        $awayXG = pow(1 / $strengthRatio, $ratioExponent) * $baseGoals;

        return [
            $this->poissonRandom($homeXG),
            $this->poissonRandom($awayXG),
        ];
    }

    /**
     * Generate a Poisson-distributed random number.
     */
    private function poissonRandom(float $lambda): int
    {
        $L = exp(-$lambda);
        $k = 0;
        $p = 1.0;

        do {
            $k++;
            $p *= mt_rand() / mt_getrandmax();
        } while ($p > $L);

        return max(0, $k - 1);
    }
}
