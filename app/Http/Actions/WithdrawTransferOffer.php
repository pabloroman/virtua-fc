<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\TransferOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WithdrawTransferOffer
{
    public function __invoke(Request $request, string $gameId, string $offerId): RedirectResponse
    {
        $game = Game::findOrFail($gameId);

        $offer = TransferOffer::with(['gamePlayer.player'])
            ->where('id', $offerId)
            ->where('game_id', $gameId)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->whereIn('status', [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_FEE_AGREED])
            ->firstOrFail();

        // Verify the offer belongs to the user's team
        if ($offer->offering_team_id !== $game->team_id) {
            abort(403);
        }

        $playerName = $offer->gamePlayer->player->name;

        $offer->update([
            'status' => TransferOffer::STATUS_REJECTED,
            'resolved_at' => $game->current_date,
        ]);

        return redirect()
            ->back()
            ->with('success', __('transfers.offer_withdrawn', ['player' => $playerName]));
    }
}
