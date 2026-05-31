<?php

namespace App\Modules\Transfer\Services;

use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\Loan;
use App\Models\ShortlistedPlayer;
use App\Models\TransferListing;
use App\Models\TransferOffer;
use App\Models\UserSquadCareerRecord;
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
        private readonly ContractService $contractService,
    ) {}
    /**
     * Complete an outgoing transfer (user's player sold to AI team).
     */
    public function completeOutgoingTransfer(TransferOffer $offer, Game $game): void
    {
        $player = $offer->gamePlayer;
        $playerName = $player->name;
        $buyer = $offer->offeringTeam;
        $buyerName = $buyer->name;
        $buyerNameWithA = $buyer->nameWithA();
        $isLoan = $offer->offer_type === TransferOffer::TYPE_LOAN_OUT;

        // Transfer player to the buying team
        TransferListing::where('game_player_id', $player->id)->delete();
        $player->update([
            'team_id' => $offer->offering_team_id,
            'number' => null,
        ]);

        // For loan-out offers, create a loan record so the player returns at season end
        if ($isLoan) {
            $effectiveStart = $game->getLoanEffectiveStartDate();
            Loan::create([
                'game_id' => $game->id,
                'game_player_id' => $player->id,
                'parent_team_id' => $game->team_id,
                'loan_team_id' => $offer->offering_team_id,
                'started_at' => $effectiveStart,
                'return_at' => $game->getSeasonEndDateFor($effectiveStart),
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

        // Update transfer budget and record the transaction.
        // firstOrCreate guarantees the (game, season) investment row exists so
        // the increment cannot be silently skipped — the visible budget and
        // the FinancialTransaction ledger must always move together.
        if ($offer->transfer_fee > 0) {
            $investment = GameInvestment::firstOrCreate([
                'game_id' => $game->id,
                'season' => (int) $game->season,
            ]);

            $investment->increment('transfer_budget', $offer->transfer_fee);

            FinancialTransaction::recordIncome(
                gameId: $game->id,
                category: FinancialTransaction::CATEGORY_TRANSFER_IN,
                amount: $offer->transfer_fee,
                description: __('finances.tx_player_sold', ['player' => $playerName, 'team' => $buyerName, 'team_a' => $buyerNameWithA]),
                transactionDate: $game->current_date,
                relatedPlayerId: $player->id,
            );
        }

        // Mark offer as completed
        $offer->update(['status' => TransferOffer::STATUS_COMPLETED, 'resolved_at' => $game->current_date]);

        // Remove from shortlist to free up scouting slot
        ShortlistedPlayer::removeForPlayer($game->id, $player->id);

        // Player has left user's club permanently — drop career record.
        // Loans preserve ownership so the record is left intact.
        if (! $isLoan) {
            UserSquadCareerRecord::where('game_player_id', $player->id)->delete();
        }
    }

    /**
     * Complete a pre-contract transfer (player joins buying team for free).
     */
    public function completePreContractTransfer(TransferOffer $offer): void
    {
        $player = $offer->gamePlayer;
        $playerName = $player->name;
        $buyer = $offer->offeringTeam;
        $buyerName = $buyer->name;
        $buyerNameWithA = $buyer->nameWithA();
        $game = $player->game;
        $fromTeamId = $player->team_id;
        $fromTeamName = $player->team->name ?? null;

        // Transfer player to the buying team
        TransferListing::where('game_player_id', $player->id)->delete();
        $player->update([
            'team_id' => $offer->offering_team_id,
            'number' => null,
            // Extend their contract with the new team
            'contract_until' => Carbon::createFromDate((int) $game->season + rand(2, 4) + 1, 6, 30),
            // Recompute the clause for the new club. This path writes no
            // annual_wage, so the wage signal is the offer's offered_wage. ES
            // default = floor; null elsewhere (and for flag-off saves).
            'release_clause' => $game->release_clauses_enabled
                ? $this->contractService->calculateReleaseClause($player->market_value_cents, $offer->offered_wage, null, $buyer->country)
                : null,
        ]);

        $this->syncCareerRecordOnOwnershipChange(
            game: $game,
            player: $player,
            previousTeamId: $fromTeamId,
            previousTeamName: $fromTeamName,
        );

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
            description: __('finances.tx_free_transfer_out', ['player' => $playerName, 'team' => $buyerName, 'team_a' => $buyerNameWithA]),
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
        // Resolve the (game, season) investment up-front via firstOrCreate so
        // the budget check and the later decrement both operate on the same
        // guaranteed row — no silent skip if the relation cache is stale or
        // the season's row hasn't been allocated yet.
        $investment = $offer->transfer_fee > 0
            ? GameInvestment::firstOrCreate([
                'game_id' => $game->id,
                'season' => (int) $game->season,
            ])
            : null;

        // Safety net: reject if budget would go negative
        if ($investment && $offer->transfer_fee > $investment->transfer_budget) {
            $offer->update(['status' => TransferOffer::STATUS_REJECTED, 'resolved_at' => $game->current_date]);
            return false;
        }

        $player = $offer->gamePlayer;
        $playerName = $player->name;
        $sellerTeam = $offer->sellingTeam ?? $player->team;
        $sellerName = $sellerTeam->name ?? 'Unknown';
        $sellerNameWithDe = $sellerTeam?->nameWithDe() ?? 'de Unknown';
        $fromTeamId = $offer->selling_team_id ?? $player->team_id;

        // Transfer player to user's team
        $age = $player->age($game->current_date);
        $contractYears = $offer->offered_years ?? ($age > PlayerAge::PRIME_END ? 2 : ($age >= PlayerAge::PRIME_END ? 3 : rand(3, 5)));
        $seasonYear = (int) $game->season;
        $newContractEnd = Carbon::createFromDate($seasonYear + $contractYears + 1, 6, 30);

        TransferListing::where('game_player_id', $player->id)->delete();
        $previousTeamId = $player->team_id;
        $player->update([
            'team_id' => $game->team_id,
            'number' => $this->squadNumberService->assignNumberForNewPlayer($game, $player),
            'contract_until' => $newContractEnd,
            'annual_wage' => $offer->offered_wage ?? $player->annual_wage,
            // Recompute the clause for the buying (user's) club — mandatory
            // floor for ES, null elsewhere (and for flag-off saves).
            'release_clause' => $game->release_clauses_enabled
                ? $this->contractService->calculateReleaseClause($player->market_value_cents, null, null, $game->country)
                : null,
        ]);

        $this->syncCareerRecordOnOwnershipChange(
            game: $game,
            player: $player,
            previousTeamId: $previousTeamId,
            previousTeamName: $sellerName,
        );

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

        // Deduct from transfer budget and record the transaction. $investment
        // was resolved at the top of the method via firstOrCreate, so the
        // decrement and the FinancialTransaction always move together.
        if ($offer->transfer_fee > 0) {
            $investment->decrement('transfer_budget', $offer->transfer_fee);

            FinancialTransaction::recordExpense(
                gameId: $game->id,
                category: FinancialTransaction::CATEGORY_TRANSFER_OUT,
                amount: $offer->transfer_fee,
                description: __('finances.tx_player_signed', ['player' => $playerName, 'team' => $sellerName, 'team_de' => $sellerNameWithDe]),
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

        $previousTeamId = $player->team_id;
        $previousTeamName = $player->team->name ?? null;

        $player->update([
            'team_id' => $game->team_id,
            'number' => $this->squadNumberService->assignNumberForNewPlayer($game, $player),
            'contract_until' => $newContractEnd,
            'annual_wage' => $offer->offered_wage,
            // Recompute the clause for the signing (user's) club — mandatory
            // floor for ES, null elsewhere (and for flag-off saves).
            'release_clause' => $game->release_clauses_enabled
                ? $this->contractService->calculateReleaseClause($player->market_value_cents, null, null, $game->country)
                : null,
        ]);

        $this->syncCareerRecordOnOwnershipChange(
            game: $game,
            player: $player,
            previousTeamId: $previousTeamId,
            previousTeamName: $previousTeamName,
            originOverride: UserSquadCareerRecord::ORIGIN_FREE_AGENT,
        );

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

    /**
     * Reconcile the user-squad career record after a player's team_id changes.
     *
     * - Player moves into a user-owned team and wasn't already user-owned:
     *   create a record. `joined_from` snapshots the previous team's name.
     * - Player moves out of a user-owned team to a non-user-owned team:
     *   delete the record. (We do not retain history for ex-players.)
     * - Both old and new team are user-owned (e.g., filial → first team):
     *   update in place; preserve accumulated season_stats.
     * - Neither side is user-owned: no-op (AI-vs-AI move).
     */
    public function syncCareerRecordOnOwnershipChange(
        Game $game,
        GamePlayer $player,
        ?string $previousTeamId,
        ?string $previousTeamName,
        ?string $originOverride = null,
    ): void {
        $wasOwned = $game->ownsTeam($previousTeamId);
        $isOwned = $game->ownsTeam($player->team_id);

        if (! $wasOwned && ! $isOwned) {
            return;
        }

        if ($wasOwned && ! $isOwned) {
            UserSquadCareerRecord::where('game_player_id', $player->id)->delete();
            return;
        }

        $origin = $originOverride ?? $previousTeamName ?? UserSquadCareerRecord::ORIGIN_ACADEMY;

        UserSquadCareerRecord::updateOrCreate(
            ['game_player_id' => $player->id],
            [
                'game_id' => $game->id,
                'team_id' => $player->team_id,
                'joined_season' => (int) $game->season,
                'joined_from' => $origin,
            ],
        );
    }
}
