<?php

namespace App\Modules\Season\Processors;

use App\Models\GamePlayer;
use App\Models\ScoutReport;
use App\Models\ShortlistedPlayer;
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

        ShortlistedPlayer::where('game_id', $game->id)->update([
            'intel_level' => ShortlistedPlayer::INTEL_SURFACE,
            'is_tracking' => false,
            'matchdays_tracked' => 0,
        ]);

        $agreedOffers = TransferOffer::where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('offer_type', '!=', TransferOffer::TYPE_PRE_CONTRACT)
            ->get(['id', 'game_player_id', 'offering_team_id', 'selling_team_id', 'direction', 'transfer_fee', 'offer_type']);

        if ($agreedOffers->isNotEmpty()) {
            \Illuminate\Support\Facades\Log::warning('[TransferBug] DELETING agreed non-pre-contract offers without completing them!', [
                'game_id' => $game->id,
                'count' => $agreedOffers->count(),
                'offers' => $agreedOffers->toArray(),
            ]);
        }

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
