<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Finance\Services\SeasonSimulationService;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use Illuminate\Support\Facades\Log;

/**
 * Generates simulated standings for non-played leagues at season end.
 *
 * Runs before PromotionRelegationProcessor (priority 26) so that
 * simulated results are available for promotion/relegation decisions.
 *
 * Priority: 24
 */
class SeasonSimulationProcessor implements SeasonProcessor
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
     * Simulate league competitions the game has entries for,
     * except the player's own league and Swiss-format competitions.
     *
     * When $forceResimulate is false (default), leagues that already have
     * simulated data are skipped to preserve results shown to the player
     * (e.g. on the season-end screen). When true, existing simulated data
     * is overwritten — used after promotion/relegation swaps change rosters.
     *
     * @param  string[]|null  $competitionIds  If provided, only simulate these competition IDs
     * @param  bool  $forceResimulate  If true, overwrite existing simulated data
     */
    public function simulateNonPlayedLeagues(Game $game, ?array $competitionIds = null, bool $forceResimulate = false): void
    {
        $query = CompetitionEntry::where('game_id', $game->id);

        if ($competitionIds !== null) {
            $query->whereIn('competition_id', $competitionIds);
        }

        $leagueIds = $query->pluck('competition_id')->unique();

        $leagues = Competition::whereIn('id', $leagueIds)
            ->where('role', Competition::ROLE_LEAGUE)
            ->whereIn('handler_type', ['league', 'league_with_playoff'])
            ->where('id', '!=', $game->competition_id)
            ->get();

        foreach ($leagues as $league) {
            // Skip if real standings exist for this competition
            $hasRealStandings = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $league->id)
                ->where('played', '>', 0)
                ->exists();

            if ($hasRealStandings) {
                continue;
            }

            // Skip if simulated data already exists (unless forced re-simulation
            // after roster changes like promotion/relegation swaps)
            if (!$forceResimulate) {
                $alreadySimulated = SimulatedSeason::where('game_id', $game->id)
                    ->where('season', $game->season)
                    ->where('competition_id', $league->id)
                    ->exists();

                if ($alreadySimulated) {
                    continue;
                }
            }

            $simulated = $this->simulationService->simulateLeague($game, $league);

            Log::info('Simulated league season', [
                'game_id' => $game->id,
                'season' => $game->season,
                'competition_id' => $league->id,
                'result_count' => count($simulated->results ?? []),
            ]);
        }
    }
}
