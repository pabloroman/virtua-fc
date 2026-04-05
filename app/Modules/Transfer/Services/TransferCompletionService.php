<?php

namespace App\Modules\Transfer\Services;

use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\Loan;
use App\Models\ShortlistedPlayer;
use App\Models\TransferListing;
use App\Models\TransferOffer;
use App\Modules\Player\PlayerAge;
use App\Modules\Squad\Services\SquadNumberService;
use App\Modules\Transfer\Enums\TransferWindowType;
use Carbon\Carbon;

/**
 * Handles the low-level completion of transfers: moving players,
 * recording GameTransfer history, and updating financials.
 *
 * Extracted from TransferService to isolate completion plumbing
 * from negotiation and offer-management logic.
 */
class TransferCompletionService
{
    public function __construct(
        private readonly SquadNumberService $squadNumberService,
    ) {}
    /**
     * Complete an outgoing transfer (user's player sold to AI team).
     */
    public function completeOutgoingTransfer(TransferOffer $offer, Game $game): void
    {
        $player = $offer->gamePlayer;
        $playerName = $player->player->name;
        $buyerName = $offer->offeringTeam->name;
        $isLoan = $offer->offer_type === TransferOffer::TYPE_LOAN_OUT;

        // Transfer player to the buying team
        TransferListing::where('game_player_id', $player->id)->delete();
        $player->update([
            'team_id' => $offer->offering_team_id,
            'number' => null,
        ]);

        // For loan-out offers, create a loan record so the player returns at season end
        if ($isLoan) {
            Loan::create([
                'game_id' => $game->id,
                'game_player_id' => $player->id,
                'parent_team_id' => $game->team_id,
                'loan_team_id' => $offer->offering_team_id,
                'started_at' => $game->current_date,
                'return_at' => $game->getSeasonEndDate(),
                'status' => Loan::STATUS_ACTIVE,
            ]);
        }

        GameTransfer::record(
            gameId: $game->id,
            gamePlayerId: $player->id,
            fromTeamId: $game->team_id,
            toTeamId: $offer->offering_team_id,
            transferFee: $offer->transfer_fee,
            type: $isLoan ? GameTransfer::TYPE_LOAN : GameTransfer::TYPE_TRANSFER,
            season: $game->season,
            window: TransferWindowType::currentValue($game->current_date),
        );

        // Update transfer budget and record the transaction
        $investment = $game->currentInvestment;
        if ($offer->transfer_fee > 0) {
            // Add transfer fee back to transfer budget
            if ($investment) {
                $investment->increment('transfer_budget', $offer->transfer_fee);
            }

            // Record the transaction
            FinancialTransaction::recordIncome(
                gameId: $game->id,
                category: FinancialTransaction::CATEGORY_TRANSFER_IN,
                amount: $offer->transfer_fee,
                description: __('finances.tx_player_sold', ['player' => $playerName, 'team' => $buyerName]),
                transactionDate: $game->current_date,
                relatedPlayerId: $player->id,
            );
        }

        // Mark offer as completed
        $offer->update(['status' => TransferOffer::STATUS_COMPLETED, 'resolved_at' => $game->current_date]);

        // Remove from shortlist to free up scouting slot
        ShortlistedPlayer::removeForPlayer($game->id, $player->id);

    }

    /**
     * Complete a pre-contract transfer (player joins buying team for free).
     */
    public function completePreContractTransfer(TransferOffer $offer): void
    {
        $player = $offer->gamePlayer;
        $playerName = $player->player->name;
        $buyerName = $offer->offeringTeam->name;
        $game = $player->game;
        $fromTeamId = $player->team_id;

        // Transfer player to the buying team
        TransferListing::where('game_player_id', $player->id)->delete();
        $player->update([
            'team_id' => $offer->offering_team_id,
            'number' => null,
            // Extend their contract with the new team
            'contract_until' => Carbon::createFromDate((int) $game->season + rand(2, 4) + 1, 6, 30),
        ]);

        GameTransfer::record(
            gameId: $game->id,
            gamePlayerId: $player->id,
            fromTeamId: $fromTeamId,
            toTeamId: $offer->offering_team_id,
            transferFee: 0,
            type: GameTransfer::TYPE_FREE_AGENT,
            season: $game->season,
            window: TransferWindowType::currentValue($game->current_date),
        );

        // Record the transaction (free transfer, but still useful to track)
        FinancialTransaction::recordIncome(
            gameId: $game->id,
            category: FinancialTransaction::CATEGORY_TRANSFER_IN,
            amount: 0,
            description: __('finances.tx_free_transfer_out', ['player' => $playerName, 'team' => $buyerName]),
            transactionDate: $game->current_date,
            relatedPlayerId: $player->id,
        );

        // Mark offer as completed
        $offer->update(['status' => TransferOffer::STATUS_COMPLETED, 'resolved_at' => $game->current_date]);

    }

    /**
     * Complete an incoming transfer (user buys player from AI team).
     */
    public function completeIncomingTransfer(TransferOffer $offer, Game $game): bool
    {
        // Safety net: reject if budget would go negative
        $investment = $game->currentInvestment;
        if ($offer->transfer_fee > 0 && $investment && $offer->transfer_fee > $investment->transfer_budget) {
            $offer->update(['status' => TransferOffer::STATUS_REJECTED, 'resolved_at' => $game->current_date]);
            return false;
        }

        $player = $offer->gamePlayer;
        $playerName = $player->player->name;
        $sellerName = $offer->sellingTeam->name ?? $player->team->name ?? 'Unknown';
        $fromTeamId = $offer->selling_team_id ?? $player->team_id;

        // Transfer player to user's team
        $age = $player->age($game->current_date);
        $contractYears = $offer->offered_years ?? ($age > PlayerAge::PRIME_END ? 2 : ($age >= PlayerAge::PRIME_END ? 3 : rand(3, 5)));
        $seasonYear = (int) $game->season;
        $newContractEnd = Carbon::createFromDate($seasonYear + $contractYears + 1, 6, 30);

        TransferListing::where('game_player_id', $player->id)->delete();
        $player->update([
            'team_id' => $game->team_id,
            'number' => $this->squadNumberService->assignNumberForNewPlayer($game, $player),
            'contract_until' => $newContractEnd,
            'annual_wage' => $offer->offered_wage ?? $player->annual_wage,
        ]);

        GameTransfer::record(
            gameId: $game->id,
            gamePlayerId: $player->id,
            fromTeamId: $fromTeamId,
            toTeamId: $game->team_id,
            transferFee: $offer->transfer_fee,
            type: GameTransfer::TYPE_TRANSFER,
            season: $game->season,
            window: TransferWindowType::currentValue($game->current_date),
        );

        // Deduct from transfer budget and record the transaction
        $investment = $game->currentInvestment;
        if ($offer->transfer_fee > 0) {
            // Deduct from transfer budget
            if ($investment) {
                $investment->decrement('transfer_budget', $offer->transfer_fee);
            }

            FinancialTransaction::recordExpense(
                gameId: $game->id,
                category: FinancialTransaction::CATEGORY_TRANSFER_OUT,
                amount: $offer->transfer_fee,
                description: __('finances.tx_player_signed', ['player' => $playerName, 'team' => $sellerName]),
                transactionDate: $game->current_date,
                relatedPlayerId: $player->id,
            );
        }

        $offer->update(['status' => TransferOffer::STATUS_COMPLETED, 'resolved_at' => $game->current_date]);

        // Remove from shortlist to free up scouting slot
        ShortlistedPlayer::removeForPlayer($game->id, $player->id);

        return true;
    }

    /**
     * Complete a free agent signing (user signs unattached player).
     */
    public function completeFreeAgentSigning(Game $game, GamePlayer $player, TransferOffer $offer): void
    {
        $seasonYear = (int) $game->season;
        $contractYears = $offer->offered_years ?? ($player->age($game->current_date) >= 32 ? 1 : 3);
        $newContractEnd = Carbon::createFromDate($seasonYear + $contractYears + 1, 6, 30);

        $player->update([
            'team_id' => $game->team_id,
            'number' => $this->squadNumberService->assignNumberForNewPlayer($game, $player),
            'contract_until' => $newContractEnd,
            'annual_wage' => $offer->offered_wage,
        ]);

        $offer->update([
            'status' => TransferOffer::STATUS_COMPLETED,
            'resolved_at' => $game->current_date,
        ]);

        GameTransfer::record(
            gameId: $game->id,
            gamePlayerId: $player->id,
            fromTeamId: null,
            toTeamId: $game->team_id,
            transferFee: 0,
            type: GameTransfer::TYPE_FREE_AGENT,
            season: $game->season,
            window: TransferWindowType::currentValue($game->current_date),
        );

        ShortlistedPlayer::removeForPlayer($game->id, $player->id);
    }

}
