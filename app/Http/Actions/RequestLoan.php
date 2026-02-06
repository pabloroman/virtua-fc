<?php

namespace App\Http\Actions;

use App\Game\Services\LoanService;
use App\Game\Services\ScoutingService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
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
            return redirect()->route('game.scouting.player', [$game->id, $player->id])
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
            ]);

            $this->loanService->processLoanIn($game, $player);

            return redirect()->route('game.scouting.player', [$game->id, $player->id])
                ->with('success', $evaluation['message'] . ' The loan is now active.');
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
        ]);

        return redirect()->route('game.scouting.player', [$game->id, $player->id])
            ->with('success', $evaluation['message'] . ' The loan will begin at the next transfer window.');
    }

    private function handleLoanOut(Game $game, GamePlayer $player)
    {
        // Check player isn't already on loan
        if ($player->isOnLoan()) {
            return redirect()->route('game.loans', $game->id)
                ->with('error', $player->name . ' is already on loan.');
        }

        // Find a destination team
        $destination = $this->loanService->findLoanDestination($game, $player);

        if (!$destination) {
            return redirect()->route('game.loans', $game->id)
                ->with('error', 'No suitable club found for loan.');
        }

        // If window is open, complete immediately
        if ($game->isTransferWindowOpen()) {
            TransferOffer::create([
                'game_id' => $game->id,
                'game_player_id' => $player->id,
                'offering_team_id' => $destination->id,
                'selling_team_id' => $game->team_id,
                'offer_type' => TransferOffer::TYPE_LOAN_OUT,
                'direction' => TransferOffer::DIRECTION_OUTGOING,
                'transfer_fee' => 0,
                'status' => TransferOffer::STATUS_COMPLETED,
                'expires_at' => $game->current_date->addDays(30),
            ]);

            $this->loanService->processLoanOut($game, $player, $destination);

            return redirect()->route('game.loans', $game->id)
                ->with('success', $player->name . ' has been loaned to ' . $destination->name . '.');
        }

        // Create agreed loan offer record (will be processed at next window)
        TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $destination->id,
            'selling_team_id' => $game->team_id,
            'offer_type' => TransferOffer::TYPE_LOAN_OUT,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'transfer_fee' => 0,
            'status' => TransferOffer::STATUS_AGREED,
            'expires_at' => $game->current_date->addDays(30),
        ]);

        return redirect()->route('game.loans', $game->id)
            ->with('success', $player->name . ' loan to ' . $destination->name . ' will begin at the next transfer window.');
    }
}
