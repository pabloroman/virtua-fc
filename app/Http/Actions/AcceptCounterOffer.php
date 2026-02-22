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
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('messages.counter_offer_expired'));
        }

        $result = $this->transferService->acceptCounterOffer($game, $offer);

        if (!$result) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('messages.bid_exceeds_budget'));
        }

        $playerName = $offer->gamePlayer->player->name ?? 'Player';

        if ($result['completed']) {
            return redirect()->route('game.transfers', $gameId)
                ->with('success', __('messages.counter_offer_accepted_immediate', ['player' => $playerName]));
        }

        $nextWindow = $game->getNextWindowName();

        return redirect()->route('game.transfers', $gameId)
            ->with('success', __('messages.counter_offer_accepted', ['player' => $playerName, 'window' => $nextWindow]));
    }
}
