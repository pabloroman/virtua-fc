<?php

namespace App\Http\Actions;

use App\Modules\Transfer\Services\TransferService;
use App\Models\Game;
use App\Models\TransferOffer;
use Illuminate\Http\RedirectResponse;

class RejectCounterOffer
{
    public function __construct(
        private readonly TransferService $transferService,
    ) {}

    public function __invoke(string $gameId, string $offerId): RedirectResponse
    {
        $game = Game::findOrFail($gameId);
        $offer = TransferOffer::with('gamePlayer.player')->where('game_id', $gameId)->findOrFail($offerId);

        if (!$offer->isPending() || !$offer->isIncoming()) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('messages.counter_offer_expired'));
        }

        $playerName = $offer->gamePlayer->player->name ?? 'Player';

        $this->transferService->rejectOffer($offer);

        return redirect()->route('game.transfers', $gameId)
            ->with('success', __('messages.counter_offer_rejected', ['player' => $playerName]));
    }
}
