<?php

namespace App\Http\Actions;

use App\Game\Services\TransferService;
use App\Models\Game;
use App\Models\TransferOffer;

class AcceptCounterOffer
{
    public function __construct(
        private readonly TransferService $transferService,
    ) {}

    public function __invoke(string $gameId, string $offerId)
    {
        $game = Game::findOrFail($gameId);
        $offer = TransferOffer::with('gamePlayer.player')->where('game_id', $gameId)->findOrFail($offerId);

        if (!$offer->isPending() || !$offer->isIncoming()) {
            return redirect()->route('game.scouting', $gameId)
                ->with('error', 'This offer is no longer available.');
        }

        // Accept counter: update transfer_fee to counter amount (stored in asking_price)
        $offer->update(['transfer_fee' => $offer->asking_price]);

        $playerName = $offer->gamePlayer->player->name ?? 'Player';

        // Complete immediately if window open, otherwise mark as agreed
        $completedImmediately = $this->transferService->acceptIncomingOffer($offer);

        if ($completedImmediately) {
            return redirect()->route('game.scouting', $gameId)
                ->with('success', "Transfer complete! {$playerName} has joined your squad.");
        }

        $nextWindow = $game->getNextWindowName();
        return redirect()->route('game.scouting', $gameId)
            ->with('success', "Counter-offer accepted! {$playerName} will join when the {$nextWindow} window opens.");
    }
}
