<?php

namespace App\Http\Actions;

use App\Modules\Transfer\Services\LoanService;
use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Http\Request;

class RequestLoan
{
    public function __construct(
        private readonly LoanService $loanService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);
        $player = GamePlayer::with(['player', 'team'])->findOrFail($playerId);

        // Determine if this is loan-in (from scouting) or loan-out (from squad)
        $isLoanOut = $player->team_id === $game->team_id;

        if ($isLoanOut) {
            return $this->handleLoanOut($game, $player);
        }

        return $this->handleLoanIn($game, $player);
    }

    private function handleLoanIn(Game $game, GamePlayer $player)
    {
        $this->loanService->requestLoanIn($game, $player);

        return redirect()->route('game.transfers', $game->id)
            ->with('success', __('messages.loan_request_submitted', ['player' => $player->player->name]));
    }

    private function handleLoanOut(Game $game, GamePlayer $player)
    {
        // Check player isn't already on loan
        if ($player->isOnLoan()) {
            return redirect()->route('game.transfers.outgoing', $game->id)
                ->with('error', __('messages.already_on_loan', ['player' => $player->name]));
        }

        // Check player isn't already searching for a loan
        if ($player->hasActiveLoanSearch()) {
            return redirect()->route('game.transfers.outgoing', $game->id)
                ->with('error', __('messages.loan_search_active', ['player' => $player->name]));
        }

        // Check transfer_status isn't already set (e.g. listed for sale)
        if ($player->transfer_status !== null) {
            return redirect()->route('game.transfers.outgoing', $game->id)
                ->with('error', __('messages.already_on_loan', ['player' => $player->name]));
        }

        $this->loanService->startLoanSearch($game, $player);

        return redirect()->route('game.transfers.outgoing', $game->id)
            ->with('success', __('messages.loan_search_started', ['player' => $player->name]));
    }
}
