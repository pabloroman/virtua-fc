<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Http\Request;

class SubmitPreContractOffer
{
    public function __construct(
        private readonly TransferService $transferService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        $player = GamePlayer::with(['player', 'team'])->findOrFail($playerId);

        $validated = $request->validate([
            'offered_wage' => 'required|integer|min:0',
        ]);

        $offeredWageCents = (int) ($validated['offered_wage'] * 100);

        try {
            $this->transferService->submitPreContractOffer($game, $player, $offeredWageCents);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('game.transfers', $gameId)
            ->with('success', __('messages.pre_contract_submitted'));
    }
}
