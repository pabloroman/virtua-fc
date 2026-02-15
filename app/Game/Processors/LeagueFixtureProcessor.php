<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Game\Services\SeasonInitializationService;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;

/**
 * Cleans up old matches/cup ties and generates league fixtures for the new season.
 *
 * current_date is finalized later by ContinentalAndCupInitProcessor (priority 106)
 * after all competitions (league, Swiss, cups) have their fixtures.
 *
 * Priority: 30 (runs after promotion/relegation at 26)
 */
class LeagueFixtureProcessor implements SeasonEndProcessor
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
        // Delete old matches
        GameMatch::where('game_id', $game->id)->delete();

        // Delete old cup ties
        CupTie::where('game_id', $game->id)->delete();

        // Generate new league fixtures via shared service
        $this->service->generateLeagueFixtures($game->id, $data->competitionId, $data->newSeason);

        return $data;
    }
}
