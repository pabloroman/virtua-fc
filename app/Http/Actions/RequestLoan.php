<?php

namespace App\Http\Actions;

use App\Modules\Transfer\Services\LoanService;
use App\Modules\Transfer\Services\ScoutingService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferOffer;
use Illuminate\Http\Request;

class RequestLoan
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
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
        // Evaluate loan request
        $evaluation = $this->scoutingService->evaluateLoanRequest($player);

        if ($evaluation['result'] === 'rejected') {
            return redirect()->route('game.transfers', $game->id)
                ->with('error', $evaluation['message']);
        }

        // If window is open, complete immediately
        if ($game->isTransferWindowOpen()) {
            TransferOffer::create([
                'game_id' => $game->id,
                'game_player_id' => $player->id,
                'offering_team_id' => $game->team_id,
                'selling_team_id' => $player->team_id,
                'offer_type' => TransferOffer::TYPE_LOAN_IN,
                'direction' => TransferOffer::DIRECTION_INCOMING,
                'transfer_fee' => 0,
                'status' => TransferOffer::STATUS_COMPLETED,
                'expires_at' => $game->current_date->addDays(30),
                'game_date' => $game->current_date,
                'resolved_at' => $game->current_date,
            ]);

            $this->loanService->processLoanIn($game, $player);

            return redirect()->route('game.transfers', $game->id)
                ->with('success', __('messages.loan_in_complete', ['message' => $evaluation['message']]));
        }

        // Create agreed loan offer record (will be processed at next window)
        TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $game->team_id,
            'selling_team_id' => $player->team_id,
            'offer_type' => TransferOffer::TYPE_LOAN_IN,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'status' => TransferOffer::STATUS_AGREED,
            'expires_at' => $game->current_date->addDays(30),
            'game_date' => $game->current_date,
            'resolved_at' => $game->current_date,
        ]);

        $nextWindow = $game->getNextWindowName();
        return redirect()->route('game.transfers', $game->id)
            ->with('success', __('messages.loan_agreed', ['message' => $evaluation['message'], 'window' => $nextWindow]));
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
