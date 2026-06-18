<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Models\Game;

/**
 * Flags career-mode games as needing pre-season opponent selection.
 *
 * The friendlies themselves are no longer generated here: the player chooses
 * their own opponents (and home/away) on the mandatory pre-season setup screen,
 * which materialises the fixtures via PreseasonOpponentService::confirmSelections.
 *
 * Priority: 108 (after ContinentalAndCupInitProcessor at 106, which sets
 * pre_season = true and current_date to July 1).
 */
class PreSeasonFixtureProcessor implements SeasonProcessor
{
    public function priority(): int
    {
        return 108;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        if (! $game->isCareerMode()) {
            return $data;
        }

        $game->update(['preseason_opponents_pending' => true]);

        return $data;
    }
}
