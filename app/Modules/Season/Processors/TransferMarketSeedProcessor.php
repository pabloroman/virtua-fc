<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Transfer\Services\TransferMarketService;

/**
 * Seeds the AI transfer market with an initial batch of listings at the end
 * of season setup. Without this, the Mercado tab stays empty until the first
 * match finalizes (CareerActionProcessor is the only other caller of
 * refreshListings), which hurt the first-time experience on a new game.
 */
class TransferMarketSeedProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly TransferMarketService $transferMarketService,
    ) {}

    public function priority(): int
    {
        return 111; // After NewSeasonReset (110) — truly last
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $this->transferMarketService->refreshListings($game);

        return $data;
    }
}
