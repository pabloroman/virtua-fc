<?php

namespace App\Game\Services;

use App\Models\Game;
use App\Models\GamePlayer;
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
     * Chance of pre-contract offer per expiring player per matchday.
     */
    private const PRE_CONTRACT_OFFER_CHANCE = 0.10; // 10%

    /**
     * Pre-contract offer expiry in days.
     */
    private const PRE_CONTRACT_OFFER_EXPIRY_DAYS = 14;

    /**
     * Winter transfer window matchday (mid-season).
     */
    private const WINTER_WINDOW_MATCHDAY = 19;

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
            'transfer_listed_at' => now(),
        ]);
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
            ->update(['status' => TransferOffer::STATUS_EXPIRED]);
    }

    /**
     * Generate offers for a newly listed player.
     */
    public function generateOffersForListedPlayer(GamePlayer $player): Collection
    {
        $offers = collect();
        $numOffers = rand(1, 3);
        $buyers = $this->getEligibleBuyers($player);

        if ($buyers->isEmpty()) {
            return $offers;
        }

        // Shuffle and take random buyers
        $selectedBuyers = $buyers->shuffle()->take($numOffers);

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
     */
    public function generateOffersForListedPlayers(Game $game): Collection
    {
        $offers = collect();

        // Get all listed players for the user's team
        $listedPlayers = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('transfer_status', GamePlayer::TRANSFER_STATUS_LISTED)
            ->get();

        foreach ($listedPlayers as $player) {
            // Skip if player already has an agreed transfer (waiting for window)
            $hasAgreedTransfer = $player->transferOffers()
                ->where('status', TransferOffer::STATUS_AGREED)
                ->exists();

            if ($hasAgreedTransfer) {
                continue;
            }

            // Check how many pending offers the player already has
            $pendingOfferCount = $player->transferOffers()
                ->where('offer_type', TransferOffer::TYPE_LISTED)
                ->where('status', TransferOffer::STATUS_PENDING)
                ->count();

            // Skip if player already has 3+ pending offers
            if ($pendingOfferCount >= 3) {
                continue;
            }

            // 40% chance of receiving a new offer each matchday
            if (rand(1, 100) <= 40) {
                $buyers = $this->getEligibleBuyers($player);

                // Exclude teams that already made offers
                $existingOfferTeamIds = $player->transferOffers()
                    ->where('status', TransferOffer::STATUS_PENDING)
                    ->pluck('offering_team_id')
                    ->toArray();

                $availableBuyers = $buyers->filter(
                    fn ($team) => !in_array($team->id, $existingOfferTeamIds)
                );

                if ($availableBuyers->isNotEmpty()) {
                    $buyer = $availableBuyers->random();
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
     */
    public function generateUnsolicitedOffers(Game $game): Collection
    {
        $offers = collect();

        // Get top players by market value who are NOT listed
        $starPlayers = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereNull('transfer_status')
            ->orderByDesc('market_value_cents')
            ->limit(self::STAR_PLAYER_COUNT)
            ->get();

        foreach ($starPlayers as $player) {
            // Skip if player already has a pending unsolicited offer
            $hasPendingOffer = $player->transferOffers()
                ->where('offer_type', TransferOffer::TYPE_UNSOLICITED)
                ->where('status', TransferOffer::STATUS_PENDING)
                ->exists();

            if ($hasPendingOffer) {
                continue;
            }

            // Random chance for an offer
            if (rand(1, 100) <= self::UNSOLICITED_OFFER_CHANCE * 100) {
                $buyers = $this->getEligibleBuyers($player);

                if ($buyers->isNotEmpty()) {
                    $buyer = $buyers->random();
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
     */
    public function generatePreContractOffers(Game $game): Collection
    {
        $offers = collect();

        // Only generate pre-contract offers in the second half of the season (matchday 19+)
        // This simulates the January window onwards
        if ($game->current_matchday < self::WINTER_WINDOW_MATCHDAY) {
            return $offers;
        }

        // Get players with expiring contracts who can receive pre-contract offers
        $expiringPlayers = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->get()
            ->filter(fn ($player) => $player->canReceivePreContractOffers());

        foreach ($expiringPlayers as $player) {
            // Skip if player already has a pending pre-contract offer
            $hasPendingOffer = $player->transferOffers()
                ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
                ->where('status', TransferOffer::STATUS_PENDING)
                ->exists();

            if ($hasPendingOffer) {
                continue;
            }

            // Random chance for an offer
            if (rand(1, 100) <= self::PRE_CONTRACT_OFFER_CHANCE * 100) {
                $buyers = $this->getEligibleBuyers($player);

                if ($buyers->isNotEmpty()) {
                    $buyer = $buyers->random();
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
        ]);
    }

    /**
     * Complete all pre-contract transfers (called at end of season).
     * Players move to their new team on a free transfer.
     */
    public function completePreContractTransfers(Game $game): Collection
    {
        $agreedPreContracts = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
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
     * Complete a single pre-contract transfer.
     * No fee, player joins the new team.
     */
    private function completePreContractTransfer(TransferOffer $offer): void
    {
        $player = $offer->gamePlayer;

        // Transfer player to the buying team
        $player->update([
            'team_id' => $offer->offering_team_id,
            'transfer_status' => null,
            'transfer_listed_at' => null,
            // Extend their contract with the new team
            'contract_until' => Carbon::parse($player->game->current_date)->addYears(rand(2, 4)),
        ]);

        // Mark offer as completed
        $offer->update(['status' => TransferOffer::STATUS_COMPLETED]);
    }

    /**
     * Get players with expiring contracts for display.
     */
    public function getExpiringContractPlayers(Game $game): Collection
    {
        return GamePlayer::with(['player', 'transferOffers' => function ($query) {
                $query->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
                    ->whereIn('status', [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_AGREED]);
            }])
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->get()
            ->filter(fn ($player) => $player->isContractExpiring())
            ->sortBy('contract_until');
    }

    /**
     * Accept a transfer offer (marks as agreed, completes at transfer window).
     */
    public function acceptOffer(TransferOffer $offer): void
    {
        $player = $offer->gamePlayer;

        // Mark offer as agreed (waiting for transfer window)
        $offer->update(['status' => TransferOffer::STATUS_AGREED]);

        // Reject all other pending offers for this player
        TransferOffer::where('game_player_id', $player->id)
            ->where('id', '!=', $offer->id)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->update(['status' => TransferOffer::STATUS_REJECTED]);
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

        // Transfer player to the buying team
        $player->update([
            'team_id' => $offer->offering_team_id,
            'transfer_status' => null,
            'transfer_listed_at' => null,
        ]);

        // Update finances - add transfer fee to balance and transfer budget
        $finances = $game->finances;
        if ($finances) {
            $finances->update([
                'balance' => $finances->balance + $offer->transfer_fee,
                'transfer_budget' => $finances->transfer_budget + $offer->transfer_fee,
            ]);
        }

        // Mark offer as completed
        $offer->update(['status' => TransferOffer::STATUS_COMPLETED]);
    }

    /**
     * Check if we're at a transfer window.
     */
    public function isTransferWindow(Game $game): bool
    {
        // Summer window: at season start (matchday 0)
        if ($game->current_matchday === 0) {
            return true;
        }

        // Winter window: around matchday 19
        if ($game->current_matchday === self::WINTER_WINDOW_MATCHDAY) {
            return true;
        }

        return false;
    }

    /**
     * Get the next transfer window description.
     */
    public function getNextTransferWindow(Game $game): string
    {
        $matchday = $game->current_matchday;

        if ($matchday < self::WINTER_WINDOW_MATCHDAY) {
            return 'Winter (Matchday ' . self::WINTER_WINDOW_MATCHDAY . ')';
        }

        return 'Summer (End of Season)';
    }

    /**
     * Reject a transfer offer.
     */
    public function rejectOffer(TransferOffer $offer): void
    {
        $offer->update(['status' => TransferOffer::STATUS_REJECTED]);
    }

    /**
     * Expire old offers.
     */
    public function expireOffers(Game $game): int
    {
        return TransferOffer::where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->where('expires_at', '<', $game->current_date)
            ->update(['status' => TransferOffer::STATUS_EXPIRED]);
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
        $game = $player->game;
        $playerTeamId = $player->team_id;
        $playerValue = $player->market_value_cents;

        // Get all teams in the same league(s) as the player's team, excluding player's team
        $leagueTeamIds = Team::whereHas('competitions', function ($query) use ($game) {
            $query->where('type', 'league');
        })->pluck('id')->toArray();

        // Filter to teams that could reasonably afford the player
        // (teams with similar or higher squad value)
        $eligibleTeams = Team::whereIn('id', $leagueTeamIds)
            ->where('id', '!=', $playerTeamId)
            ->get()
            ->filter(function ($team) use ($game, $playerValue) {
                // Calculate team's squad value
                $squadValue = GamePlayer::where('game_id', $game->id)
                    ->where('team_id', $team->id)
                    ->sum('market_value_cents');

                // Team should have at least 20% of player's value in total squad
                // (rough proxy for "can afford")
                return $squadValue >= $playerValue * 0.2;
            });

        return $eligibleTeams;
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
     * Get listed players for a game.
     */
    public function getListedPlayers(Game $game): Collection
    {
        return GamePlayer::with(['player', 'activeOffers.offeringTeam'])
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('transfer_status', GamePlayer::TRANSFER_STATUS_LISTED)
            ->orderByDesc('market_value_cents')
            ->get();
    }
}
