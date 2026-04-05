<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferListing;
use App\Modules\Transfer\Services\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CancelLoanSearch
{
    public function __construct(
        private readonly LoanService $loanService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId): RedirectResponse
    {
        $game = Game::findOrFail($gameId);
        $player = GamePlayer::where('id', $playerId)
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->whereHas('transferListing', fn ($q) => $q->where('status', TransferListing::STATUS_LOAN_SEARCH))
            ->firstOrFail();

        $this->loanService->cancelLoanSearch($player);

        return redirect()
            ->route('game.transfers.outgoing', $gameId)
            ->with('success', __('messages.loan_search_cancelled', ['player' => $player->player->name]));
    }
}
