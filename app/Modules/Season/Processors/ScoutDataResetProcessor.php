<?php

namespace App\Modules\Season\Processors;

use App\Models\GamePlayer;
use App\Models\ScoutReport;
use App\Models\ShortlistedPlayer;
use App\Models\TransferOffer;
use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Models\Game;

/**
 * Clears scouting and transfer market data for the new season.
 * Priority: 20 (runs after settlement so transfer offer history is available for wage calculations)
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

        TransferOffer::where('game_id', $game->id)->delete();

        GamePlayer::where('game_id', $game->id)
            ->whereNotNull('transfer_status')
            ->update([
                'transfer_status' => null,
                'transfer_listed_at' => null,
            ]);

        return $data;
    }
}
