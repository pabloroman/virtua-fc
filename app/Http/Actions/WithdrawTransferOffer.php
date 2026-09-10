<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\TransferOffer;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WithdrawTransferOffer
{
    public function __construct(
        private readonly TransferService $transferService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $offerId): RedirectResponse
    {
        $game = Game::findOrFail($gameId);

        $offer = TransferOffer::with(['gamePlayer'])
            ->where('id', $offerId)
            ->where('game_id', $gameId)
            ->incoming()
            ->whereIn('status', [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_FEE_AGREED])
            ->firstOrFail();

        // Verify the offer belongs to the user's team
        if ($offer->offering_team_id !== $game->team_id) {
            abort(403);
        }

        $playerName = $offer->gamePlayer->name;

        $this->transferService->rejectOffer($offer);

        return redirect()
            ->back()
            ->with('success', __('transfers.offer_withdrawn', ['player' => $playerName]));
    }
}
