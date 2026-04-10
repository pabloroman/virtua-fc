<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Transfer\Services\TransferHeaderService;
use App\Modules\Transfer\Services\TransferMarketService;
use Illuminate\Http\Request;

class ShowTransferMarket
{
    public function __construct(
        private readonly TransferMarketService $transferMarketService,
        private readonly TransferHeaderService $headerService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        $isTransferWindow = $game->isTransferWindowOpen();
        $listings = $this->transferMarketService->getMarketListings($game);

        $headerData = $this->headerService->getHeaderData($game);

        $availableBudget = $game->currentInvestment?->transfer_budget ?? 0;

        return view('transfer-market', [
            'game' => $game,
            'listings' => $listings,
            'isTransferWindow' => $isTransferWindow,
            'availableBudget' => $availableBudget,
        ] + $headerData);
    }
}
