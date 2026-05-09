<?php

namespace App\Modules\Season\Processors;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Modules\Match\Services\SyntheticLeagueResolver;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use Illuminate\Support\Facades\Log;

/**
 * Defensive pass at season close: finalizes any flat-league competition the
 * user never opened during the season. Synthetic standings produced here
 * back promotion/relegation, UEFA qualifier selection, and season summaries
 * for leagues that would otherwise have empty standings.
 *
 * Runs before SeasonSimulationProcessor (priority 75). Once real standings
 * exist (played > 0), SeasonSimulationProcessor's existing skip rule
 * silently no-ops for the same competitions.
 *
 * Priority: 74
 */
class FinalizeOtherLeaguesProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly SyntheticLeagueResolver $resolver,
    ) {}

    public function priority(): int
    {
        return 74;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $leagueIds = CompetitionEntry::where('game_id', $game->id)
            ->pluck('competition_id')
            ->unique();

        $leagues = Competition::whereIn('id', $leagueIds)
            ->where('role', Competition::ROLE_LEAGUE)
            ->whereIn('handler_type', ['league', 'league_with_playoff'])
            ->where('id', '!=', $game->competition_id)
            ->get();

        // current_date is "the next match". To finalize a season we need a
        // cutoff that includes every fixture, so push it well past the
        // calendar — end-of-season for any league lands well within a year.
        $endOfSeason = $game->current_date?->copy()->addYear();

        foreach ($leagues as $league) {
            try {
                $this->resolver->catchUp($game, $league, $endOfSeason);
            } catch (\Throwable $e) {
                Log::warning('[FinalizeOtherLeagues] Failed to finalize league', [
                    'game_id' => $game->id,
                    'competition_id' => $league->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $data;
    }
}
