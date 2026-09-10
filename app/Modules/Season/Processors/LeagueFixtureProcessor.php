<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Services\SeasonInitializationService;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Support\Facades\DB;

/**
 * Cleans up old matches/cup ties and generates league fixtures for the new season.
 *
 * current_date is finalized later by ContinentalAndCupInitProcessor after all
 * competitions (league, Swiss, cups) have their fixtures.
 *
 * Runs in the setup pipeline, so the closing pipeline's promotion/relegation
 * has already placed every team in its new division.
 */
class LeagueFixtureProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly SeasonInitializationService $service,
    ) {}

    public function priority(): int
    {
        return 30;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Pre-delete cascading children by game_id (indexed) so the subsequent
        // game_matches DELETE has nothing to cascade. Cuts the per-row cost of
        // the cascade from ~1.5ms to a single bulk DELETE.
        DB::table('match_attendances')->where('game_id', $game->id)->delete();

        GameMatch::where('game_id', $game->id)->delete();
        CupTie::where('game_id', $game->id)->delete();

        $this->service->generateLeagueFixtures($game->id, $data->competitionId, $data->newSeason);

        return $data;
    }
}
