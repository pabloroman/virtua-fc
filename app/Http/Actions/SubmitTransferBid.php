<?php

namespace App\Http\Actions;

use App\Modules\Transfer\Services\ScoutingService;
use App\Modules\Transfer\Services\TransferService;
use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Http\Request;

class SubmitTransferBid
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        $player = GamePlayer::with(['player', 'team'])->findOrFail($playerId);

        $validated = $request->validate([
            'bid_amount' => 'required|numeric|min:0',
        ]);

        $bidAmountCents = (int) ($validated['bid_amount'] * 100);

        $offer = $this->transferService->submitBid($game, $player, $bidAmountCents, $this->scoutingService);

        if (!$offer) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('messages.bid_exceeds_budget'));
        }

        return redirect()->route('game.transfers', $gameId)
            ->with('success', __('messages.bid_submitted', ['player' => $player->player->name]));
    }
}
