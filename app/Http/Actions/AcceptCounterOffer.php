<?php

namespace App\Http\Actions;

use App\Modules\Transfer\Services\TransferService;
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
                ->with('error', __('messages.counter_offer_expired'));
        }

        // Accept counter: update transfer_fee to counter amount (stored in asking_price)
        $offer->update(['transfer_fee' => $offer->asking_price]);

        $playerName = $offer->gamePlayer->player->name ?? 'Player';

        // Complete immediately if window open, otherwise mark as agreed
        $completedImmediately = $this->transferService->acceptIncomingOffer($offer);

        if ($completedImmediately) {
            return redirect()->route('game.scouting', $gameId)
                ->with('success', __('messages.counter_offer_accepted_immediate', ['player' => $playerName]));
        }

        $nextWindow = $game->getNextWindowName();
        return redirect()->route('game.scouting', $gameId)
            ->with('success', __('messages.counter_offer_accepted', ['player' => $playerName, 'window' => $nextWindow]));
    }
}
