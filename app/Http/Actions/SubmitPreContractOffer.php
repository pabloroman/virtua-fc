<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Finance\Services\SalaryCapService;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Http\Request;

class SubmitPreContractOffer
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly SalaryCapService $salaryCapService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        $player = GamePlayer::where('game_id', $gameId)->with(['team'])->findOrFail($playerId);

        if ($player->isUserOwned($game)) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('transfers.cannot_target_own_player'));
        }

        $validated = $request->validate([
            'offered_wage' => 'required|integer|min:0',
        ]);

        $offeredWageCents = (int) ($validated['offered_wage'] * 100);

        // Salary cap: a pre-contract is a free signing whose wage starts next
        // season, so it is gated on next season's committed bill — not this
        // season's, which still carries contracts and loans that expire before
        // the new wage ever begins.
        if (! $this->salaryCapService->canCommitNextSeasonWage($game, $offeredWageCents)) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', $this->salaryCapService->nextSeasonBlockMessage($game, $player->name, $offeredWageCents));
        }

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
