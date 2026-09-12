<?php

namespace App\Modules\Season\Processors;

use App\Models\ScoutReport;
use App\Models\TransferListing;
use App\Models\TransferOffer;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Transfer\Exceptions\AgreedOfferDiscardedException;
use App\Models\Game;

/**
 * Clears scouting and transfer market data for the new season.
 * Runs after every agreed deal has completed and after settlement, so
 * transfer offer history is still available for wage calculations.
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

        // The delete below is unconditional and leaves no trace, so anything
        // still fully agreed at this point disappears without a GameTransfer
        // row, without a notification, and without an offer to inspect after
        // the fact. Every agreed deal should already have been completed (or
        // rejected loudly) at priority 30/35 — surface the ones that were not
        // instead of erasing them silently. See AgreedOfferDiscardedException.
        TransferOffer::where('game_id', $game->id)
            ->agreed()
            ->get()
            ->each(fn (TransferOffer $offer) => report(AgreedOfferDiscardedException::forOffer($offer)));

        TransferOffer::where('game_id', $game->id)->delete();

        TransferListing::where('game_id', $game->id)->delete();

        return $data;
    }
}
