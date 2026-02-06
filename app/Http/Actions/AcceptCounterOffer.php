<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\TransferOffer;

class AcceptCounterOffer
{
    public function __invoke(string $gameId, string $offerId)
    {
        $game = Game::findOrFail($gameId);
        $offer = TransferOffer::where('game_id', $gameId)->findOrFail($offerId);

        if (!$offer->isPending() || !$offer->isIncoming()) {
            return redirect()->route('game.scouting', $gameId)
                ->with('error', 'This offer is no longer available.');
        }

        // Accept counter: update transfer_fee to counter amount (stored in asking_price) and agree
        $offer->update([
            'transfer_fee' => $offer->asking_price,
            'status' => TransferOffer::STATUS_AGREED,
        ]);

        $playerName = $offer->gamePlayer->name ?? 'Player';

        return redirect()->route('game.scouting.player', [$gameId, $offer->game_player_id])
            ->with('success', "Counter-offer accepted! Deal agreed for {$playerName}.");
    }
}
