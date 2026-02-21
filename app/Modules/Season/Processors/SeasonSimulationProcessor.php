<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Finance\Services\SeasonSimulationService;
use App\Models\Competition;
use App\Models\CompetitionEntry;
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
        $this->simulateNonPlayedLeagues($game);

        return $data;
    }

    /**
     * Simulate all league competitions the game has entries for,
     * except the player's own league and Swiss-format competitions.
     */
    public function simulateNonPlayedLeagues(Game $game): void
    {
        $leagueIds = CompetitionEntry::where('game_id', $game->id)
            ->pluck('competition_id')
            ->unique();

        $leagues = Competition::whereIn('id', $leagueIds)
            ->where('role', Competition::ROLE_LEAGUE)
            ->whereIn('handler_type', ['league', 'league_with_playoff'])
            ->where('id', '!=', $game->competition_id)
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
    }
}
