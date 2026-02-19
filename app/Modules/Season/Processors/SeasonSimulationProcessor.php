<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Finance\Services\SeasonSimulationService;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameStanding;

/**
 * Generates simulated standings for non-played leagues at season end.
 *
 * Runs before PromotionRelegationProcessor (priority 26) so that
 * simulated results are available for promotion/relegation decisions.
 *
 * Priority: 24
 */
class SeasonSimulationProcessor implements SeasonEndProcessor
{
    public function __construct(
        private readonly SeasonSimulationService $simulationService,
    ) {}

    public function priority(): int
    {
        return 24;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $userCompetition = Competition::find($game->competition_id);

        if (!$userCompetition) {
            return $data;
        }

        // Find leagues in the same country that need simulation
        $leagues = Competition::where('country', $userCompetition->country)
            ->where('role', Competition::ROLE_LEAGUE)
            ->where('id', '!=', $userCompetition->id)
            ->get();

        foreach ($leagues as $league) {
            // Only simulate if no real standings exist for this competition
            $hasRealStandings = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $league->id)
                ->where('played', '>', 0)
                ->exists();

            if (!$hasRealStandings) {
                $this->simulationService->simulateLeague($game, $league);
            }
        }

        return $data;
    }
}
