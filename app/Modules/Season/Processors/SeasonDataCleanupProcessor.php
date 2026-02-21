<?php

namespace App\Modules\Season\Processors;

use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;

/**
 * Wipes stale season data before new-season initialization begins.
 *
 * Deletes all old matches, cup ties, and Swiss standings so that
 * downstream processors (PrimaryCompetitionInitProcessor,
 * SecondaryCompetitionInitProcessor) start from a clean slate.
 *
 * Priority: 10 (runs first among setup processors)
 */
class SeasonDataCleanupProcessor implements SeasonEndProcessor
{
    public function __construct(
        private readonly CountryConfig $countryConfig,
    ) {}

    public function priority(): int
    {
        return 10;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Delete all old matches (league, Swiss, cup)
        GameMatch::where('game_id', $game->id)->delete();

        // Delete all old cup ties
        CupTie::where('game_id', $game->id)->delete();

        // Delete stale Swiss standings (teams may change between seasons)
        $countryCode = $game->country ?? 'ES';
        $swissIds = $this->countryConfig->swissFormatCompetitionIds($countryCode);

        foreach ($swissIds as $competitionId) {
            GameStanding::where('game_id', $game->id)
                ->where('competition_id', $competitionId)
                ->delete();
        }

        return $data;
    }
}
