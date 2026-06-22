<?php

namespace App\Modules\Season\Processors;

use App\Models\ScoutReport;
use App\Models\TransferListing;
use App\Models\TransferOffer;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Models\Game;

/**
 * Clears scouting and transfer market data for the new season.
 * Priority: 20 (runs after settlement so transfer offer history is available for wage calculations)
 */
class TransferMarketResetProcessor implements SeasonProcessor
{
    public function priority(): int
    {
        return 70;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        ScoutReport::where('game_id', $game->id)->delete();

        TransferOffer::where('game_id', $game->id)->delete();

        TransferListing::where('game_id', $game->id)->delete();

        return $data;
    }
}
