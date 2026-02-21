<?php

namespace App\Modules\Season\Processors;

use App\Models\ScoutReport;
use App\Models\ShortlistedPlayer;
use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Models\Game;

/**
 * Clears scout reports and shortlisted players for the new season.
 * Priority: 20 (runs alongside stats reset)
 */
class ScoutDataResetProcessor implements SeasonEndProcessor
{
    public function priority(): int
    {
        return 20;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        ScoutReport::where('game_id', $game->id)->delete();

        ShortlistedPlayer::where('game_id', $game->id)->delete();

        return $data;
    }
}
