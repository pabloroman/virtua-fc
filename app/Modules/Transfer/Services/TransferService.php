<?php

namespace App\Modules\Transfer\Services;

use App\Modules\Transfer\Services\ScoutingService;
use App\Models\Competition;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\Loan;
use App\Models\Team;
use App\Models\TransferOffer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TransferService
{
    /**
     * Discount range for listed players (buyer has leverage).
     */
    private const LISTED_PRICE_MIN = 0.85;
    private const LISTED_PRICE_MAX = 0.95;

    /**
     * Premium range for unsolicited offers (tempting the seller).
     */
    private const UNSOLICITED_PRICE_MIN = 1.00;
    private const UNSOLICITED_PRICE_MAX = 1.20;

    /**
     * Age adjustments for transfer pricing.
     */
    private const AGE_PREMIUM_UNDER_23 = 1.10;  // Young talent premium
    private const AGE_PENALTY_PER_YEAR_OVER_29 = 0.05;  // 5% per year

    /**
     * Offer expiry in days.
     */
    private const LISTED_OFFER_EXPIRY_DAYS = 7;
    private const UNSOLICITED_OFFER_EXPIRY_DAYS = 5;

    /**
     * Chance of unsolicited offer per star player per matchday.
     */
    private const UNSOLICITED_OFFER_CHANCE = 0.05; // 5%

    /**
     * Number of star players to consider for unsolicited offers.
     */
    private const STAR_PLAYER_COUNT = 5;

    /**
     * Maximum transfer fee as a fraction of the buying team's squad value.
     * Prevents small clubs from making unrealistically large bids.
     */
    private const MAX_FEE_TO_SQUAD_VALUE_RATIO = 0.25;

    /**
     * Chance of pre-contract offer per expiring player per matchday.
     */
    private const PRE_CONTRACT_OFFER_CHANCE = 0.10; // 10%

    /**
     * Pre-contract offer expiry in days.
     */
    private const PRE_CONTRACT_OFFER_EXPIRY_DAYS = TransferOffer::PRE_CONTRACT_OFFER_EXPIRY_DAYS;

    /**
     * Transfer windows configuration.
     */
    public const WINDOW_SUMMER = 'summer';
    public const WINDOW_WINTER = 'winter';

    /**
     * List a player for transfer.
     */
    public function listPlayer(GamePlayer $player): void
    {
        $player->update([
            'transfer_status' => GamePlayer::TRANSFER_STATUS_LISTED,
            'transfer_listed_at' => $player->game->current_date,
        ]);
    }

    /**
     * Determine the current transfer window for a game.
     * Returns 'summer' if the summer window is open (or as default for season-end pre-contracts),
     * 'winter' if the winter window is open.
     */
    public function getCurrentWindow(Game $game): string
    {
        if ($game->isWinterWindowOpen()) {
            return self::WINDOW_WINTER;
        }

        return self::WINDOW_SUMMER;
    }

    /**
     * Remove a player from the transfer list.
     */
    public function unlistPlayer(GamePlayer $player): void
    {
        $player->update([
            'transfer_status' => null,
            'transfer_listed_at' => null,
        ]);

        // Expire any pending offers
        $player->transferOffers()
            ->where('status', TransferOffer::STATUS_PENDING)
            ->update([
                'status' => TransferOffer::STATUS_EXPIRED,
                'resolved_at' => $player->game->current_date,
            ]);
    }

    /**
     * Generate offers for a newly listed player.
     */
    public function generateOffersForListedPlayer(GamePlayer $player): Collection
    {
        $offers = collect();
        $numOffers = rand(1, 3);
        ['buyers' => $buyers, 'squadValues' => $squadValues] = $this->getEligibleBuyersWithSquadValues($player);

        if ($buyers->isEmpty()) {
            return $offers;
        }

        // Select buyers weighted by player trajectory and team strength
        $selectedBuyers = $this->selectWeightedBuyers($buyers, $player, $squadValues, $numOffers);

        foreach ($selectedBuyers as $buyer) {
            $offer = $this->createOffer(
                player: $player,
                offeringTeam: $buyer,
                offerType: TransferOffer::TYPE_LISTED,
            );
            $offers->push($offer);
        }

        return $offers;
    }

    /**
     * Generate offers for all listed players.
     * Called on each matchday advance.
     *
     * @param Collection|null $allPlayersGrouped Pre-loaded players grouped by team_id (optional, for N+1 optimization)
     */
    public function generateOffersForListedPlayers(Game $game, $allPlayersGrouped = null): Collection
    {
        $offers = collect();

        // Use pre-loaded players if available, otherwise load
        if ($allPlayersGrouped !== null) {
            $teamPlayers = $allPlayersGrouped->get($game->team_id, collect());
            $listedPlayers = $teamPlayers->filter(
                fn ($p) => $p->transfer_status === GamePlayer::TRANSFER_STATUS_LISTED
            );
        } else {
            $listedPlayers = GamePlayer::with('transferOffers')
                ->where('game_id', $game->id)
                ->where('team_id', $game->team_id)
                ->where('transfer_status', GamePlayer::TRANSFER_STATUS_LISTED)
                ->get();
        }

        foreach ($listedPlayers as $player) {
            // Use pre-loaded transferOffers relationship to avoid N+1
            $playerOffers = $player->relationLoaded('transferOffers')
                ? $player->transferOffers
                : $player->transferOffers()->get();

            // Skip if player already has an agreed transfer (waiting for window)
            $hasAgreedTransfer = $playerOffers
                ->where('status', TransferOffer::STATUS_AGREED)
                ->isNotEmpty();

            if ($hasAgreedTransfer) {
                continue;
            }

            // Check how many pending offers the player already has
            $pendingOffers = $playerOffers
                ->where('offer_type', TransferOffer::TYPE_LISTED)
                ->where('status', TransferOffer::STATUS_PENDING);

            // Skip if player already has 3+ pending offers
            if ($pendingOffers->count() >= 3) {
                continue;
            }

            // 40% chance of receiving a new offer each matchday
            if (rand(1, 100) <= 40) {
                ['buyers' => $buyers, 'squadValues' => $squadValues] = $this->getEligibleBuyersWithSquadValues($player);

                // Exclude teams that already made offers
                $existingOfferTeamIds = $playerOffers
                    ->where('status', TransferOffer::STATUS_PENDING)
                    ->pluck('offering_team_id')
                    ->toArray();

                $availableBuyers = $buyers->filter(
                    fn ($team) => !in_array($team->id, $existingOfferTeamIds)
                );

                if ($availableBuyers->isNotEmpty()) {
                    $buyer = $this->selectWeightedBuyer($availableBuyers, $player, $squadValues);
                    $offer = $this->createOffer(
                        player: $player,
                        offeringTeam: $buyer,
                        offerType: TransferOffer::TYPE_LISTED,
                    );
                    $offers->push($offer);
                }
            }
        }

        return $offers;
    }

    /**
     * Generate unsolicited offers for star players.
     * Called on each matchday advance.
     *
     * @param Collection|null $allPlayersGrouped Pre-loaded players grouped by team_id (optional, for N+1 optimization)
     */
    public function generateUnsolicitedOffers(Game $game, $allPlayersGrouped = null): Collection
    {
        $offers = collect();

        // Use pre-loaded players if available, otherwise load
        if ($allPlayersGrouped !== null) {
            $teamPlayers = $allPlayersGrouped->get($game->team_id, collect());
            $starPlayers = $teamPlayers
                ->filter(fn ($p) => $p->transfer_status === null)
                ->sortByDesc('market_value_cents')
                ->take(self::STAR_PLAYER_COUNT);
        } else {
            $starPlayers = GamePlayer::with('transferOffers')
                ->where('game_id', $game->id)
                ->where('team_id', $game->team_id)
                ->whereNull('transfer_status')
                ->orderByDesc('market_value_cents')
                ->limit(self::STAR_PLAYER_COUNT)
                ->get();
        }

        foreach ($starPlayers as $player) {
            // Use pre-loaded transferOffers relationship to avoid N+1
            $playerOffers = $player->relationLoaded('transferOffers')
                ? $player->transferOffers
                : $player->transferOffers()->get();

            // Skip if player already has a pending unsolicited offer
            $hasPendingOffer = $playerOffers
                ->where('offer_type', TransferOffer::TYPE_UNSOLICITED)
                ->where('status', TransferOffer::STATUS_PENDING)
                ->isNotEmpty();

            if ($hasPendingOffer) {
                continue;
            }

            // Random chance for an offer
            if (rand(1, 100) <= self::UNSOLICITED_OFFER_CHANCE * 100) {
                ['buyers' => $buyers, 'squadValues' => $squadValues] = $this->getEligibleBuyersWithSquadValues($player);

                if ($buyers->isNotEmpty()) {
                    $buyer = $this->selectWeightedBuyer($buyers, $player, $squadValues);
                    $offer = $this->createOffer(
                        player: $player,
                        offeringTeam: $buyer,
                        offerType: TransferOffer::TYPE_UNSOLICITED,
                    );
                    $offers->push($offer);
                }
            }
        }

        return $offers;
    }

    /**
     * Generate pre-contract offers for players with expiring contracts.
     * Called on each matchday advance (typically from January onwards).
     *
     * @param Collection|null $allPlayersGrouped Pre-loaded players grouped by team_id (optional, for N+1 optimization)
     */
    public function generatePreContractOffers(Game $game, $allPlayersGrouped = null): Collection
    {
        $offers = collect();

        // Only generate pre-contract offers from January through May
        // Players can sign pre-contracts 6 months before their contract expires (June 30)
        if (!$game->current_date) {
            return $offers;
        }

        $month = $game->current_date->month;
        if ($month < 1 || $month > 5) {
            return $offers;
        }

        $seasonEndDate = $game->getSeasonEndDate();

        // Use pre-loaded players if available, otherwise load
        if ($allPlayersGrouped !== null) {
            $teamPlayers = $allPlayersGrouped->get($game->team_id, collect());
            // Filter to players with expiring contracts who can receive offers
            $expiringPlayers = $teamPlayers->filter(function ($player) use ($seasonEndDate) {
                // Check if contract is expiring
                if (!$player->contract_until || !$player->contract_until->lte($seasonEndDate)) {
                    return false;
                }
                // Retiring players won't sign pre-contracts
                if ($player->isRetiring()) {
                    return false;
                }
                // Check if they have pending_annual_wage (renewal agreed)
                if ($player->pending_annual_wage !== null) {
                    return false;
                }
                // Use pre-loaded transferOffers to check for existing agreements
                $playerOffers = $player->relationLoaded('transferOffers')
                    ? $player->transferOffers
                    : collect();
                $hasPreContract = $playerOffers
                    ->where('status', TransferOffer::STATUS_AGREED)
                    ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
                    ->isNotEmpty();
                $hasAgreedTransfer = $playerOffers
                    ->where('status', TransferOffer::STATUS_AGREED)
                    ->isNotEmpty();

                if ($hasPreContract || $hasAgreedTransfer) {
                    return false;
                }

                // Active negotiation blocks pre-contract offers
                if ($player->relationLoaded('activeRenewalNegotiation') && $player->activeRenewalNegotiation) {
                    return false;
                }

                return true;
            });
        } else {
            $expiringPlayers = GamePlayer::with(['game', 'transferOffers', 'latestRenewalNegotiation', 'activeRenewalNegotiation'])
                ->where('game_id', $game->id)
                ->where('team_id', $game->team_id)
                ->get()
                ->filter(fn ($player) => $player->canReceivePreContractOffers($seasonEndDate));
        }

        foreach ($expiringPlayers as $player) {
            // Use pre-loaded transferOffers relationship to avoid N+1
            $playerOffers = $player->relationLoaded('transferOffers')
                ? $player->transferOffers
                : $player->transferOffers()->get();

            // Skip if player already has a pending pre-contract offer
            $hasPendingOffer = $playerOffers
                ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
                ->where('status', TransferOffer::STATUS_PENDING)
                ->isNotEmpty();

            if ($hasPendingOffer) {
                continue;
            }

            // Random chance for an offer
            if (rand(1, 100) <= self::PRE_CONTRACT_OFFER_CHANCE * 100) {
                ['buyers' => $buyers, 'squadValues' => $squadValues] = $this->getEligibleBuyersWithSquadValues($player);

                if ($buyers->isNotEmpty()) {
                    $buyer = $this->selectWeightedBuyer($buyers, $player, $squadValues);
                    $offer = $this->createPreContractOffer($player, $buyer);
                    $offers->push($offer);
                }
            }
        }

        return $offers;
    }

    /**
     * Create a pre-contract offer for a player.
     */
    private function createPreContractOffer(GamePlayer $player, Team $offeringTeam): TransferOffer
    {
        return TransferOffer::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $player->game_id,
            'game_player_id' => $player->id,
            'offering_team_id' => $offeringTeam->id,
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'transfer_fee' => 0, // Free transfer
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => Carbon::parse($player->game->current_date)->addDays(self::PRE_CONTRACT_OFFER_EXPIRY_DAYS),
            'game_date' => $player->game->current_date,
        ]);
    }

    /**
     * Complete all pre-contract transfers (called at end of season).
     * Players move to their new team on a free transfer.
     * This handles outgoing pre-contracts (AI clubs taking user's players).
     */
    public function completePreContractTransfers(Game $game): Collection
    {
        $agreedPreContracts = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->where(function ($query) {
                $query->whereNull('direction')
                    ->orWhere('direction', '!=', TransferOffer::DIRECTION_INCOMING);
            })
            ->whereHas('gamePlayer', function ($query) use ($game) {
                $query->where('team_id', $game->team_id);
            })
            ->get();

        $completedTransfers = collect();

        foreach ($agreedPreContracts as $offer) {
            $this->completePreContractTransfer($offer);
            $completedTransfers->push($offer);
        }

        return $completedTransfers;
    }

    /**
     * Complete all incoming pre-contract transfers (called at end of season).
     * Players the user signed on pre-contracts join the team.
     */
    public function completeIncomingPreContracts(Game $game): Collection
    {
        $agreedIncoming = TransferOffer::with(['gamePlayer.player', 'sellingTeam'])
            ->where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->get();

        $completedTransfers = collect();

        foreach ($agreedIncoming as $offer) {
            $this->completeIncomingTransfer($offer, $game);
            $completedTransfers->push($offer);
        }

        return $completedTransfers;
    }

    /**
     * Resolve pending incoming pre-contract offers after the response delay.
     * Called each matchday; evaluates offers where enough time has passed.
     */
    public function resolveIncomingPreContractOffers(Game $game, ScoutingService $scoutingService): Collection
    {
        $responseDate = $game->current_date->subDays(TransferOffer::PRE_CONTRACT_RESPONSE_DAYS);

        $pendingOffers = TransferOffer::with(['gamePlayer.player'])
            ->where('game_id', $game->id)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->where('game_date', '<=', $responseDate)
            ->get();

        $resolvedOffers = collect();

        foreach ($pendingOffers as $offer) {
            $evaluation = $scoutingService->evaluatePreContractOffer($offer->gamePlayer, $offer->offered_wage);

            $offer->update([
                'status' => $evaluation['accepted'] ? TransferOffer::STATUS_AGREED : TransferOffer::STATUS_REJECTED,
                'resolved_at' => $game->current_date,
            ]);

            $resolvedOffers->push([
                'offer' => $offer,
                'accepted' => $evaluation['accepted'],
            ]);
        }

        return $resolvedOffers;
    }

    /**
     * Resolve pending incoming bids after the next matchday.
     * Called each matchday; evaluates user bids that haven't been resolved yet.
     */
    public function resolveIncomingBids(Game $game, ScoutingService $scoutingService): Collection
    {
        $pendingBids = TransferOffer::with(['gamePlayer.player', 'gamePlayer.team'])
            ->where('game_id', $game->id)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->where('offer_type', TransferOffer::TYPE_USER_BID)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->whereNull('resolved_at')
            ->get();

        $resolvedOffers = collect();

        foreach ($pendingBids as $offer) {
            $evaluation = $scoutingService->evaluateBid($offer->gamePlayer, $offer->transfer_fee);

            if ($evaluation['result'] === 'accepted') {
                $offer->update([
                    'asking_price' => $evaluation['asking_price'],
                ]);
                $completedImmediately = $this->acceptIncomingOffer($offer);

                $resolvedOffers->push([
                    'offer' => $offer->fresh(),
                    'result' => 'accepted',
                    'completed' => $completedImmediately,
                ]);
            } elseif ($evaluation['result'] === 'counter') {
                $offer->update([
                    'asking_price' => $evaluation['counter_amount'],
                    'resolved_at' => $game->current_date,
                ]);

                $resolvedOffers->push([
                    'offer' => $offer->fresh(),
                    'result' => 'counter',
                    'completed' => false,
                ]);
            } else {
                $offer->update([
                    'status' => TransferOffer::STATUS_REJECTED,
                    'asking_price' => $evaluation['asking_price'],
                    'resolved_at' => $game->current_date,
                ]);

                $resolvedOffers->push([
                    'offer' => $offer->fresh(),
                    'result' => 'rejected',
                    'completed' => false,
                ]);
            }
        }

        return $resolvedOffers;
    }

    /**
     * Resolve pending incoming loan requests after the next matchday.
     * Called each matchday; evaluates user loan requests that haven't been resolved yet.
     */
    public function resolveIncomingLoanRequests(Game $game, ScoutingService $scoutingService): Collection
    {
        $pendingLoans = TransferOffer::with(['gamePlayer.player', 'gamePlayer.team'])
            ->where('game_id', $game->id)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->where('offer_type', TransferOffer::TYPE_LOAN_IN)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->whereNull('resolved_at')
            ->get();

        $resolvedOffers = collect();

        foreach ($pendingLoans as $offer) {
            $evaluation = $scoutingService->evaluateLoanRequest($offer->gamePlayer);

            if ($evaluation['result'] === 'accepted') {
                if ($game->isTransferWindowOpen()) {
                    $this->completeLoanIn($offer, $game);
                    $resolvedOffers->push([
                        'offer' => $offer->fresh(),
                        'result' => 'accepted',
                        'completed' => true,
                    ]);
                } else {
                    $offer->update([
                        'status' => TransferOffer::STATUS_AGREED,
                        'resolved_at' => $game->current_date,
                    ]);
                    $resolvedOffers->push([
                        'offer' => $offer->fresh(),
                        'result' => 'accepted',
                        'completed' => false,
                    ]);
                }
            } else {
                $offer->update([
                    'status' => TransferOffer::STATUS_REJECTED,
                    'resolved_at' => $game->current_date,
                ]);

                $resolvedOffers->push([
                    'offer' => $offer->fresh(),
                    'result' => 'rejected',
                    'completed' => false,
                ]);
            }
        }

        return $resolvedOffers;
    }

    /**
     * Complete a single pre-contract transfer.
     * No fee, player joins the new team.
     */
    private function completePreContractTransfer(TransferOffer $offer): void
    {
        $player = $offer->gamePlayer;
        $playerName = $player->player->name;
        $buyerName = $offer->offeringTeam->name;
        $game = $player->game;
        $fromTeamId = $player->team_id;

        // Transfer player to the buying team
        $player->update([
            'team_id' => $offer->offering_team_id,
            'number' => GamePlayer::nextAvailableNumber($game->id, $offer->offering_team_id),
            'transfer_status' => null,
            'transfer_listed_at' => null,
            // Extend their contract with the new team
            'contract_until' => Carbon::parse($game->current_date)->addYears(rand(2, 4)),
        ]);

        GameTransfer::record(
            gameId: $game->id,
            gamePlayerId: $player->id,
            fromTeamId: $fromTeamId,
            toTeamId: $offer->offering_team_id,
            transferFee: 0,
            type: GameTransfer::TYPE_FREE_AGENT,
            season: $game->season,
            window: $this->getCurrentWindow($game),
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
     * Accept a transfer offer.
     * If transfer window is open, completes immediately.
     * If outside window, marks as agreed and completes when next window opens.
     *
     * @return bool True if transfer completed immediately, false if waiting for window
     */
    public function acceptOffer(TransferOffer $offer): bool
    {
        $player = $offer->gamePlayer;
        $game = $offer->game;

        // Reject all other pending offers for this player
        TransferOffer::where('game_player_id', $player->id)
            ->where('id', '!=', $offer->id)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->update(['status' => TransferOffer::STATUS_REJECTED, 'resolved_at' => $game->current_date]);

        // Pre-contract transfers always wait until end of season — the player's
        // current contract is still valid until June regardless of window status.
        if ($offer->isPreContract()) {
            $offer->update(['status' => TransferOffer::STATUS_AGREED, 'resolved_at' => $game->current_date]);
            return false;
        }

        // If transfer window is open, complete immediately
        if ($game->isTransferWindowOpen()) {
            $this->completeTransfer($offer, $game);
            return true;
        }

        // Otherwise, mark as agreed (waiting for next transfer window)
        $offer->update(['status' => TransferOffer::STATUS_AGREED, 'resolved_at' => $game->current_date]);
        return false;
    }

    /**
     * Complete all agreed transfers (called at transfer window).
     */
    public function completeAgreedTransfers(Game $game): Collection
    {
        $agreedOffers = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->whereHas('gamePlayer', function ($query) use ($game) {
                $query->where('team_id', $game->team_id);
            })
            ->get();

        $completedTransfers = collect();

        foreach ($agreedOffers as $offer) {
            $this->completeTransfer($offer, $game);
            $completedTransfers->push($offer);
        }

        return $completedTransfers;
    }

    /**
     * Complete a single transfer.
     */
    private function completeTransfer(TransferOffer $offer, Game $game): void
    {
        $player = $offer->gamePlayer;
        $playerName = $player->player->name;
        $buyerName = $offer->offeringTeam->name;
        $isLoan = $offer->offer_type === TransferOffer::TYPE_LOAN_OUT;

        // Transfer player to the buying team
        $player->update([
            'team_id' => $offer->offering_team_id,
            'number' => GamePlayer::nextAvailableNumber($game->id, $offer->offering_team_id),
            'transfer_status' => null,
            'transfer_listed_at' => null,
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
            window: $this->getCurrentWindow($game),
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
    }

    /**
     * Reject a transfer offer.
     */
    public function rejectOffer(TransferOffer $offer): void
    {
        $offer->update([
            'status' => TransferOffer::STATUS_REJECTED,
            'resolved_at' => $offer->game->current_date,
        ]);
    }

    /**
     * Expire old offers.
     */
    public function expireOffers(Game $game): int
    {
        return TransferOffer::where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->where('expires_at', '<', $game->current_date)
            ->update(['status' => TransferOffer::STATUS_EXPIRED, 'resolved_at' => $game->current_date]);
    }

    /**
     * Create an offer for a player.
     */
    private function createOffer(GamePlayer $player, Team $offeringTeam, string $offerType): TransferOffer
    {
        $transferFee = $this->calculateOfferPrice($player, $offerType);
        $expiryDays = $offerType === TransferOffer::TYPE_LISTED
            ? self::LISTED_OFFER_EXPIRY_DAYS
            : self::UNSOLICITED_OFFER_EXPIRY_DAYS;

        return TransferOffer::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $player->game_id,
            'game_player_id' => $player->id,
            'offering_team_id' => $offeringTeam->id,
            'offer_type' => $offerType,
            'transfer_fee' => $transferFee,
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => Carbon::parse($player->game->current_date)->addDays($expiryDays),
            'game_date' => $player->game->current_date,
        ]);
    }

    /**
     * Calculate offer price based on player and offer type.
     */
    public function calculateOfferPrice(GamePlayer $player, string $offerType): int
    {
        $baseValue = $player->market_value_cents;

        // Type modifier
        if ($offerType === TransferOffer::TYPE_LISTED) {
            $typeModifier = self::LISTED_PRICE_MIN + (mt_rand() / mt_getrandmax()) * (self::LISTED_PRICE_MAX - self::LISTED_PRICE_MIN);
        } else {
            $typeModifier = self::UNSOLICITED_PRICE_MIN + (mt_rand() / mt_getrandmax()) * (self::UNSOLICITED_PRICE_MAX - self::UNSOLICITED_PRICE_MIN);
        }

        // Age modifier
        $age = $player->age;
        $ageModifier = 1.0;

        if ($age < 23) {
            $ageModifier = self::AGE_PREMIUM_UNDER_23;
        } elseif ($age > 29) {
            $yearsOver29 = $age - 29;
            $ageModifier = max(0.5, 1.0 - ($yearsOver29 * self::AGE_PENALTY_PER_YEAR_OVER_29));
        }

        $finalPrice = (int) ($baseValue * $typeModifier * $ageModifier);

        // Round to nearest 100K (cents)
        return (int) (round($finalPrice / 10_000_000) * 10_000_000);
    }

    /**
     * Get eligible AI teams to make offers for a player.
     */
    public function getEligibleBuyers(GamePlayer $player): Collection
    {
        return $this->getEligibleBuyersWithSquadValues($player)['buyers'];
    }

    /**
     * Get eligible AI teams and their squad values in a single pass.
     * Avoids redundant squad value queries when the caller also needs weights.
     *
     * @return array{buyers: Collection, squadValues: Collection}
     */
    private function getEligibleBuyersWithSquadValues(GamePlayer $player): array
    {
        $game = $player->game;
        $playerTeamId = $player->team_id;
        $playerValue = $player->market_value_cents;

        // Get all teams in domestic leagues (both playable and foreign), excluding player's team
        $leagueTeamIds = Team::whereHas('competitions', function ($query) {
            $query->where('scope', Competition::SCOPE_DOMESTIC)
                ->where('type', 'league');
        })->where('id', '!=', $playerTeamId)->pluck('id')->toArray();

        $squadValues = $this->getSquadValues($game, $leagueTeamIds);

        // Filter to teams whose squad value can support the transfer fee
        // A team's max bid is capped at 30% of their total squad value
        $eligibleTeamIds = $squadValues
            ->filter(fn ($totalValue) => $totalValue * self::MAX_FEE_TO_SQUAD_VALUE_RATIO >= $playerValue)
            ->keys()
            ->toArray();

        $buyers = Team::whereIn('id', $eligibleTeamIds)->get();

        return ['buyers' => $buyers, 'squadValues' => $squadValues];
    }

    /**
     * Get squad total market values for a set of teams.
     */
    private function getSquadValues(Game $game, array $teamIds): Collection
    {
        return GamePlayer::where('game_id', $game->id)
            ->whereIn('team_id', $teamIds)
            ->selectRaw('team_id, SUM(market_value_cents) as total_value')
            ->groupBy('team_id')
            ->pluck('total_value', 'team_id');
    }

    /**
     * Select a buyer weighted by player trajectory and team strength.
     *
     * Growing players (≤23) attract offers from stronger teams.
     * Declining players (≥29) attract offers from weaker teams.
     * Peak players (24-28) attract offers uniformly.
     */
    private function selectWeightedBuyer(Collection $buyers, GamePlayer $player, Collection $squadValues): Team
    {
        if ($buyers->count() === 1) {
            return $buyers->first();
        }

        $weights = $this->calculateBuyerWeights($buyers, $player, $squadValues);

        return $this->weightedRandom($buyers, $weights);
    }

    /**
     * Select multiple buyers weighted by player trajectory and team strength.
     * Returns up to $count unique teams, selected without replacement.
     */
    private function selectWeightedBuyers(Collection $buyers, GamePlayer $player, Collection $squadValues, int $count): Collection
    {
        if ($buyers->count() <= $count) {
            return $buyers;
        }

        $remaining = $buyers->values();
        $selected = collect();

        for ($i = 0; $i < $count; $i++) {
            $weights = $this->calculateBuyerWeights($remaining, $player, $squadValues);
            $buyer = $this->weightedRandom($remaining, $weights);
            $selected->push($buyer);
            $remaining = $remaining->reject(fn ($t) => $t->id === $buyer->id)->values();
        }

        return $selected;
    }

    /**
     * Calculate buyer weights based on player trajectory and team strength.
     *
     * Uses squad total value as a proxy for team reputation/tier.
     * Normalizes to a 0-1 strength ratio, then applies trajectory-based weighting:
     * - Declining: weaker teams are up to 3x more likely than the strongest
     * - Growing: stronger teams are up to 3x more likely than the weakest
     * - Peak: uniform weights (all teams equally likely)
     *
     * @return array<string, float> Team ID => weight
     */
    private function calculateBuyerWeights(Collection $buyers, GamePlayer $player, Collection $squadValues): array
    {
        $developmentStatus = $player->development_status;

        // Peak players: no weighting needed
        if ($developmentStatus === 'peak') {
            return $buyers->mapWithKeys(fn ($team) => [$team->id => 1.0])->all();
        }

        $values = $buyers->map(fn ($team) => $squadValues->get($team->id, 0));
        $minValue = $values->min();
        $maxValue = $values->max();
        $range = $maxValue - $minValue;

        // If all teams have roughly the same value, use uniform weights
        if ($range == 0) {
            return $buyers->mapWithKeys(fn ($team) => [$team->id => 1.0])->all();
        }

        $weights = [];
        foreach ($buyers as $team) {
            $teamValue = $squadValues->get($team->id, 0);
            // 0 = weakest eligible buyer, 1 = strongest
            $strengthRatio = ($teamValue - $minValue) / $range;

            $weights[$team->id] = match ($developmentStatus) {
                // Declining players: weaker teams weighted higher (3:1 ratio)
                'declining' => 1.0 + 2.0 * (1.0 - $strengthRatio),
                // Growing players: stronger teams weighted higher (3:1 ratio)
                'growing' => 1.0 + 2.0 * $strengthRatio,
                default => 1.0,
            };
        }

        return $weights;
    }

    /**
     * Pick a random item from a collection using weighted probabilities.
     *
     * @param Collection $items Collection of items (must have 'id' property)
     * @param array<string, float> $weights Item ID => weight
     */
    private function weightedRandom(Collection $items, array $weights): mixed
    {
        $totalWeight = array_sum($weights);
        $random = (mt_rand() / mt_getrandmax()) * $totalWeight;

        $cumulative = 0.0;
        foreach ($items as $item) {
            $cumulative += $weights[$item->id];
            if ($random <= $cumulative) {
                return $item;
            }
        }

        return $items->last();
    }

    /**
     * Get all pending offers for a game.
     */
    public function getPendingOffers(Game $game): Collection
    {
        return TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->where('expires_at', '>=', $game->current_date)
            ->orderByDesc('transfer_fee')
            ->get();
    }

    /**
     * Complete all agreed incoming transfers (user buying/loaning players).
     * Called when transfer window opens.
     */
    public function completeIncomingTransfers(Game $game): Collection
    {
        $agreedIncoming = TransferOffer::with(['gamePlayer.player', 'sellingTeam'])
            ->where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->where('offer_type', '!=', TransferOffer::TYPE_PRE_CONTRACT)
            ->get();

        // Also get loan-out agreements
        $agreedLoanOuts = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('offer_type', TransferOffer::TYPE_LOAN_OUT)
            ->get();

        $completedTransfers = collect();

        foreach ($agreedIncoming as $offer) {
            if ($offer->offer_type === TransferOffer::TYPE_LOAN_IN) {
                $this->completeLoanIn($offer, $game);
            } else {
                $this->completeIncomingTransfer($offer, $game);
            }
            $completedTransfers->push($offer);
        }

        foreach ($agreedLoanOuts as $offer) {
            $this->completeLoanOut($offer, $game);
            $completedTransfers->push($offer);
        }

        return $completedTransfers;
    }

    /**
     * Accept an incoming transfer offer (user buying a player).
     * If transfer window is open, completes immediately.
     * If outside window, marks as agreed and completes when next window opens.
     *
     * @return bool True if transfer completed immediately, false if waiting for window
     */
    public function acceptIncomingOffer(TransferOffer $offer): bool
    {
        $game = $offer->game;

        // If transfer window is open, complete immediately
        if ($game->isTransferWindowOpen()) {
            if ($offer->offer_type === TransferOffer::TYPE_LOAN_IN) {
                $this->completeLoanIn($offer, $game);
            } else {
                $this->completeIncomingTransfer($offer, $game);
            }
            return true;
        }

        // Otherwise, mark as agreed (waiting for next transfer window)
        $offer->update(['status' => TransferOffer::STATUS_AGREED, 'resolved_at' => $game->current_date]);
        return false;
    }

    /**
     * Complete a single incoming transfer (user buys player).
     */
    private function completeIncomingTransfer(TransferOffer $offer, Game $game): void
    {
        // Safety net: reject if budget would go negative
        $investment = $game->currentInvestment;
        if ($offer->transfer_fee > 0 && $investment && $offer->transfer_fee > $investment->transfer_budget) {
            $offer->update(['status' => TransferOffer::STATUS_REJECTED, 'resolved_at' => $game->current_date]);
            return;
        }

        $player = $offer->gamePlayer;
        $playerName = $player->player->name;
        $sellerName = $offer->sellingTeam->name ?? $player->team->name ?? 'Unknown';
        $fromTeamId = $offer->selling_team_id ?? $player->team_id;

        // Transfer player to user's team
        $age = $player->age;
        $contractYears = $age >= 33 ? 2 : ($age >= 30 ? 3 : rand(3, 5));
        $newContractEnd = Carbon::parse($game->current_date)->addYears($contractYears);

        $player->update([
            'team_id' => $game->team_id,
            'number' => GamePlayer::nextAvailableNumber($game->id, $game->team_id),
            'transfer_status' => null,
            'transfer_listed_at' => null,
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
            window: $this->getCurrentWindow($game),
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
    }

    /**
     * Complete a loan-in (player joins user's team on loan).
     */
    private function completeLoanIn(TransferOffer $offer, Game $game): void
    {
        $player = $offer->gamePlayer;
        $parentTeamId = $player->team_id;
        $returnDate = $game->getSeasonEndDate();

        Loan::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'parent_team_id' => $parentTeamId,
            'loan_team_id' => $game->team_id,
            'started_at' => $game->current_date,
            'return_at' => $returnDate,
            'status' => Loan::STATUS_ACTIVE,
        ]);

        $player->update([
            'team_id' => $game->team_id,
            'number' => GamePlayer::nextAvailableNumber($game->id, $game->team_id),
        ]);

        GameTransfer::record(
            gameId: $game->id,
            gamePlayerId: $player->id,
            fromTeamId: $parentTeamId,
            toTeamId: $game->team_id,
            transferFee: 0,
            type: GameTransfer::TYPE_LOAN,
            season: $game->season,
            window: $this->getCurrentWindow($game),
        );

        $offer->update(['status' => TransferOffer::STATUS_COMPLETED, 'resolved_at' => $game->current_date]);
    }

    /**
     * Calculate the available transfer budget (transfer_budget minus committed pending/agreed offers).
     */
    public function availableBudget(Game $game): int
    {
        $investment = $game->currentInvestment;
        $committed = TransferOffer::committedBudget($game->id);

        return ($investment->transfer_budget ?? 0) - $committed;
    }

    /**
     * Submit a pre-contract offer for an expiring player (user-initiated).
     *
     * @throws \InvalidArgumentException
     */
    public function submitPreContractOffer(Game $game, GamePlayer $player, int $offeredWageCents): TransferOffer
    {
        if (!$game->isPreContractPeriod()) {
            throw new \InvalidArgumentException(__('messages.pre_contract_not_available'));
        }

        $seasonEnd = $game->getSeasonEndDate();
        if (!$player->contract_until || !$player->contract_until->lte($seasonEnd)) {
            throw new \InvalidArgumentException(__('messages.player_not_expiring'));
        }

        $existingOffer = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->whereIn('status', [TransferOffer::STATUS_AGREED, TransferOffer::STATUS_PENDING])
            ->exists();

        if ($existingOffer) {
            throw new \InvalidArgumentException(__('transfers.already_bidding'));
        }

        return TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $game->team_id,
            'selling_team_id' => $player->team_id,
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'offered_wage' => $offeredWageCents,
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => $game->current_date->addDays(TransferOffer::PRE_CONTRACT_OFFER_EXPIRY_DAYS),
            'game_date' => $game->current_date,
        ]);
    }

    /**
     * Submit a new bid for a player. Returns the created offer or null if budget is insufficient.
     */
    public function submitBid(Game $game, GamePlayer $player, int $bidAmountCents, ScoutingService $scoutingService): TransferOffer
    {
        if ($bidAmountCents > $this->availableBudget($game)) {
            throw new \InvalidArgumentException(__('messages.bid_exceeds_budget'));
        }

        // Prevent duplicate pending bids for the same player
        $existingBid = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('offering_team_id', $game->team_id)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->exists();

        if ($existingBid) {
            throw new \InvalidArgumentException(__('transfers.already_bidding'));

        }

        $wageDemand = $scoutingService->calculateWageDemand($player);

        return TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $game->team_id,
            'selling_team_id' => $player->team_id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => $bidAmountCents,
            'offered_wage' => $wageDemand,
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => $game->current_date->addDays(30),
            'game_date' => $game->current_date,
        ]);
    }

    /**
     * Accept a counter-offer. Returns ['completed' => bool] on success or null if budget is insufficient.
     */
    public function acceptCounterOffer(Game $game, TransferOffer $offer): ?array
    {
        $counterAmount = $offer->asking_price;

        // Committed budget already includes this offer's transfer_fee — subtract it to avoid double-counting
        $available = $this->availableBudget($game) + $offer->transfer_fee;

        if ($counterAmount > $available) {
            return null;
        }

        $offer->update(['transfer_fee' => $counterAmount]);

        $completedImmediately = $this->acceptIncomingOffer($offer);

        return ['completed' => $completedImmediately];
    }

    /**
     * Complete a loan-out (user's player goes to AI team).
     */
    private function completeLoanOut(TransferOffer $offer, Game $game): void
    {
        $player = $offer->gamePlayer;
        $destinationTeamId = $offer->offering_team_id;
        $returnDate = $game->getSeasonEndDate();

        Loan::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'parent_team_id' => $game->team_id,
            'loan_team_id' => $destinationTeamId,
            'started_at' => $game->current_date,
            'return_at' => $returnDate,
            'status' => Loan::STATUS_ACTIVE,
        ]);

        $player->update([
            'team_id' => $destinationTeamId,
            'number' => GamePlayer::nextAvailableNumber($game->id, $destinationTeamId),
            'transfer_status' => null,
            'transfer_listed_at' => null,
        ]);

        GameTransfer::record(
            gameId: $game->id,
            gamePlayerId: $player->id,
            fromTeamId: $game->team_id,
            toTeamId: $destinationTeamId,
            transferFee: 0,
            type: GameTransfer::TYPE_LOAN,
            season: $game->season,
            window: $this->getCurrentWindow($game),
        );

        $offer->update(['status' => TransferOffer::STATUS_COMPLETED, 'resolved_at' => $game->current_date]);
    }
}
