<?php

namespace App\Modules\Transfer\Services;

use App\Modules\Player\PlayerAge;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\LoanService;
use App\Modules\Transfer\Services\ScoutingService;
use App\Support\Money;
use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\ShortlistedPlayer;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Models\TransferOffer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TransferService
{
    public function __construct(
        private readonly LoanService $loanService,
        private readonly TransferCompletionService $completionService,
    ) {}

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
    private const AGE_PREMIUM_YOUNG = 1.10;  // Young talent premium
    private const AGE_DECLINE_PENALTY_PER_YEAR = 0.05;  // 5% per year

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
     * Default chance of pre-contract offer per expiring player per matchday.
     */
    private const PRE_CONTRACT_OFFER_CHANCE = 0.10; // 10%

    /**
     * Value-based pre-contract offer chance per matchday.
     * Higher-value players attract more aggressive AI competition.
     * [min_market_value_cents => chance]
     */
    private const PRE_CONTRACT_OFFER_CHANCE_BY_VALUE = [
        5_000_000_000  => 0.35, // €50M+ → 35%
        2_000_000_000  => 0.25, // €20M+ → 25%
        1_000_000_000  => 0.20, // €10M+ → 20%
        500_000_000    => 0.15, // €5M+  → 15%
        0              => 0.10, // < €5M → 10%
    ];

    /**
     * Pre-contract offer expiry in days.
     */
    private const PRE_CONTRACT_OFFER_EXPIRY_DAYS = TransferOffer::PRE_CONTRACT_OFFER_EXPIRY_DAYS;

    /**
     * Minimum player tier (1-5, from PlayerTierService) for a buyer
     * to be interested, keyed by reputation tier.
     */
    private const MIN_TIER_BY_REPUTATION = [
        ClubProfile::REPUTATION_LOCAL        => 1,
        ClubProfile::REPUTATION_MODEST       => 1,
        ClubProfile::REPUTATION_ESTABLISHED  => 2,
        ClubProfile::REPUTATION_CONTINENTAL  => 3,
        ClubProfile::REPUTATION_ELITE        => 4,
    ];

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
        if ($player->isOnLoan()) {
            return;
        }

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
    private function getCurrentWindow(Game $game): string
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
     * Generate offers for all listed players.
     * Called on each matchday advance.
     *
     * @param Collection|null $allPlayersGrouped Pre-loaded players grouped by team_id (optional, for N+1 optimization)
     */
    public function generateOffersForListedPlayers(Game $game, $allPlayersGrouped = null, ?array $buyerPool = null): Collection
    {
        $offers = collect();

        // Use pre-loaded players if available, otherwise load
        if ($allPlayersGrouped !== null) {
            $teamPlayers = $allPlayersGrouped->get($game->team_id, collect());
            $listedPlayers = $teamPlayers->filter(
                fn ($p) => $p->transfer_status === GamePlayer::TRANSFER_STATUS_LISTED
                    && !$p->isLoanedIn($game->team_id)
            );
        } else {
            $listedPlayers = GamePlayer::with('transferOffers')
                ->where('game_id', $game->id)
                ->where('team_id', $game->team_id)
                ->where('transfer_status', GamePlayer::TRANSFER_STATUS_LISTED)
                ->whereDoesntHave('activeLoan')
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
                ['buyers' => $buyers, 'squadValues' => $squadValues] = $this->getEligibleBuyersWithSquadValues($player, $buyerPool);

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
     * @param  array{leagueTeams: Collection, squadValues: Collection}|null  $buyerPool  Pre-loaded pool from loadBuyerPool()
     */
    public function generateUnsolicitedOffers(Game $game, $allPlayersGrouped = null, ?array $buyerPool = null): Collection
    {
        $offers = collect();

        // Use pre-loaded players if available, otherwise load
        if ($allPlayersGrouped !== null) {
            $teamPlayers = $allPlayersGrouped->get($game->team_id, collect());
            $starPlayers = $teamPlayers
                ->filter(fn ($p) => $p->transfer_status === null
                    && !$p->isLoanedIn($game->team_id))
                ->sortByDesc('market_value_cents')
                ->take(self::STAR_PLAYER_COUNT);
        } else {
            $starPlayers = GamePlayer::with('transferOffers')
                ->where('game_id', $game->id)
                ->where('team_id', $game->team_id)
                ->whereNull('transfer_status')
                ->whereDoesntHave('activeLoan')
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
                ['buyers' => $buyers, 'squadValues' => $squadValues] = $this->getEligibleBuyersWithSquadValues($player, $buyerPool);

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
     * Get the pre-contract offer chance for a player based on their market value.
     * Higher-value players attract more aggressive AI competition.
     */
    public function getPreContractOfferChance(GamePlayer $player): float
    {
        foreach (self::PRE_CONTRACT_OFFER_CHANCE_BY_VALUE as $minValue => $chance) {
            if ($player->market_value_cents >= $minValue) {
                return $chance;
            }
        }

        return self::PRE_CONTRACT_OFFER_CHANCE;
    }

    /**
     * Generate pre-contract offers for players with expiring contracts.
     * Called on each matchday advance (typically from January onwards).
     *
     * @param Collection|null $allPlayersGrouped Pre-loaded players grouped by team_id (optional, for N+1 optimization)
     * @param  array{leagueTeams: Collection, squadValues: Collection}|null  $buyerPool  Pre-loaded pool from loadBuyerPool()
     */
    public function generatePreContractOffers(Game $game, $allPlayersGrouped = null, ?array $buyerPool = null): Collection
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
            $expiringPlayers = $teamPlayers->filter(function ($player) use ($seasonEndDate, $game) {
                // Check if contract is expiring
                if (!$player->contract_until || !$player->contract_until->lte($seasonEndDate)) {
                    return false;
                }
                // Skip loaned-in players — they belong to their parent club
                if ($player->isLoanedIn($game->team_id)) {
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
                ->whereDoesntHave('activeLoan')
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

            // Random chance for an offer (scales with player market value)
            $offerChance = $this->getPreContractOfferChance($player);
            if (rand(1, 100) <= $offerChance * 100) {
                ['buyers' => $buyers, 'squadValues' => $squadValues] = $this->getEligibleBuyersWithSquadValues($player, $buyerPool);

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
            $this->completeIncomingTransfer($offer, $game, skipSquadCheck: true);
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
            $evaluation = $scoutingService->evaluatePreContractOffer($offer->gamePlayer, $offer->offered_wage, $game->team);

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
     * Complete a single pre-contract transfer.
     * No fee, player joins the new team.
     */
    private function completePreContractTransfer(TransferOffer $offer): void
    {
        $this->completionService->completePreContractTransfer($offer);
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
        $this->completionService->completeOutgoingTransfer($offer, $game);
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
    private function calculateOfferPrice(GamePlayer $player, string $offerType): int
    {
        $baseValue = $player->market_value_cents;

        // Type modifier
        if ($offerType === TransferOffer::TYPE_LISTED) {
            $typeModifier = self::LISTED_PRICE_MIN + (mt_rand() / mt_getrandmax()) * (self::LISTED_PRICE_MAX - self::LISTED_PRICE_MIN);
        } else {
            $typeModifier = self::UNSOLICITED_PRICE_MIN + (mt_rand() / mt_getrandmax()) * (self::UNSOLICITED_PRICE_MAX - self::UNSOLICITED_PRICE_MIN);
        }

        // Age modifier
        $age = $player->age($player->game->current_date);
        $ageModifier = 1.0;

        if ($age < PlayerAge::YOUNG_END) {
            $ageModifier = self::AGE_PREMIUM_YOUNG;
        } elseif ($age > PlayerAge::primePhaseAge(0.5)) {
            $yearsOverMidPrime = $age - PlayerAge::primePhaseAge(0.5);
            $ageModifier = max(0.5, 1.0 - ($yearsOverMidPrime * self::AGE_DECLINE_PENALTY_PER_YEAR));
        }

        $finalPrice = (int) ($baseValue * $typeModifier * $ageModifier);

        return Money::roundPrice($finalPrice);
    }

    /**
     * Get eligible AI teams to make offers for a player.
     */
    private function getEligibleBuyers(GamePlayer $player): Collection
    {
        return $this->getEligibleBuyersWithSquadValues($player)['buyers'];
    }

    /**
     * Pre-load the buyer pool (league teams + squad values) for a game.
     * Call once per career action tick and pass to offer-generation methods.
     *
     * @return array{leagueTeams: Collection, squadValues: Collection}
     */
    public function loadBuyerPool(Game $game): array
    {
        $leagueTeamIds = Team::transferMarketEligible()
            ->whereHas('competitions', function ($query) {
                $query->where('scope', Competition::SCOPE_DOMESTIC)
                    ->where('type', 'league');
            })->where('id', '!=', $game->team_id)->pluck('id')->toArray();

        $squadValues = $this->getSquadValues($game, $leagueTeamIds);
        $leagueTeams = Team::whereIn('id', $leagueTeamIds)->get()->keyBy('id');
        $reputationLevels = TeamReputation::resolveLevels($game->id, $leagueTeamIds);

        return ['leagueTeams' => $leagueTeams, 'squadValues' => $squadValues, 'reputationLevels' => $reputationLevels];
    }

    /**
     * Get eligible AI teams and their squad values in a single pass.
     * Avoids redundant squad value queries when the caller also needs weights.
     *
     * @param  array{leagueTeams: Collection, squadValues: Collection}|null  $buyerPool  Pre-loaded pool from loadBuyerPool()
     * @return array{buyers: Collection, squadValues: Collection}
     */
    private function getEligibleBuyersWithSquadValues(GamePlayer $player, ?array $buyerPool = null): array
    {
        $playerTeamId = $player->team_id;
        $playerValue = $player->market_value_cents;
        $playerTier = $player->tier;

        if ($buyerPool) {
            $squadValues = $buyerPool['squadValues'];
            $leagueTeams = $buyerPool['leagueTeams'];
            $reputationLevels = $buyerPool['reputationLevels'];

            // Filter to teams whose squad value can support the transfer fee
            $eligibleTeamIds = $squadValues
                ->filter(fn ($totalValue) => $totalValue * self::MAX_FEE_TO_SQUAD_VALUE_RATIO >= $playerValue)
                ->keys()
                ->toArray();

            $buyers = $leagueTeams->only($eligibleTeamIds)
                ->reject(fn ($team) => $team->id === $playerTeamId)
                ->reject(function ($team) use ($reputationLevels, $playerTier) {
                    $reputation = $reputationLevels[$team->id] ?? ClubProfile::REPUTATION_LOCAL;
                    $minTier = self::MIN_TIER_BY_REPUTATION[$reputation] ?? 1;

                    return $playerTier < $minTier;
                })
                ->values();

            return ['buyers' => $buyers, 'squadValues' => $squadValues];
        }

        $game = $player->game;

        // Get all teams in domestic leagues (both playable and foreign), excluding player's team
        $leagueTeamIds = Team::transferMarketEligible()
            ->whereHas('competitions', function ($query) {
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

        // Filter out teams whose reputation demands higher quality than the player offers
        $reputationLevels = TeamReputation::resolveLevels($game->id, $eligibleTeamIds);
        $eligibleTeamIds = collect($eligibleTeamIds)
            ->reject(function ($teamId) use ($reputationLevels, $playerTier) {
                $reputation = $reputationLevels[$teamId] ?? ClubProfile::REPUTATION_LOCAL;
                $minTier = self::MIN_TIER_BY_REPUTATION[$reputation] ?? 1;

                return $playerTier < $minTier;
            })
            ->values()
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
        $developmentStatus = $player->developmentStatus($player->game->current_date);

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
                $this->loanService->completeLoanIn($offer, $game);
            } else {
                $this->completeIncomingTransfer($offer, $game);
            }
            $completedTransfers->push($offer);
        }

        foreach ($agreedLoanOuts as $offer) {
            $this->loanService->completeLoanOut($offer, $game);
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
    /**
     * Sign a free agent: assign to team, create offer/transfer records.
     */
    public function signFreeAgent(Game $game, GamePlayer $player, int $wageDemand): TransferOffer
    {
        $seasonYear = (int) $game->season;
        $contractYears = $player->age($game->current_date) >= 32 ? 1 : mt_rand(2, 3);
        $newContractEnd = Carbon::createFromDate($seasonYear + $contractYears + 1, 6, 30);

        $player->update([
            'team_id' => $game->team_id,
            'number' => GamePlayer::nextAvailableNumber($game->id, $game->team_id),
            'contract_until' => $newContractEnd,
            'annual_wage' => $wageDemand,
        ]);

        $offer = TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $game->team_id,
            'selling_team_id' => null,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'offered_wage' => $wageDemand,
            'status' => TransferOffer::STATUS_COMPLETED,
            'resolved_at' => $game->current_date,
            'expires_at' => $game->current_date,
            'game_date' => $game->current_date,
        ]);

        GameTransfer::record(
            gameId: $game->id,
            gamePlayerId: $player->id,
            fromTeamId: null,
            toTeamId: $game->team_id,
            transferFee: 0,
            type: GameTransfer::TYPE_FREE_AGENT,
            season: $game->season,
            window: $this->getCurrentWindow($game),
        );

        ShortlistedPlayer::removeForPlayer($game->id, $player->id);

        return $offer;
    }

    /**
     * Complete a free agent signing from a negotiated offer (wage already agreed).
     */
    public function completeFreeAgentSigning(Game $game, GamePlayer $player, TransferOffer $offer): void
    {
        $this->completionService->completeFreeAgentSigning($game, $player, $offer);
    }

    public function acceptIncomingOffer(TransferOffer $offer): bool
    {
        $game = $offer->game;

        // If transfer window is open, complete immediately
        if ($game->isTransferWindowOpen()) {
            if ($offer->offer_type === TransferOffer::TYPE_LOAN_IN) {
                $this->loanService->completeLoanIn($offer, $game);
                return true;
            }

            return $this->completeIncomingTransfer($offer, $game);
        }

        // Otherwise, mark as agreed (waiting for next transfer window)
        $offer->update(['status' => TransferOffer::STATUS_AGREED, 'resolved_at' => $game->current_date]);
        return false;
    }

    /**
     * Complete a single incoming transfer (user buys player).
     *
     * @param  bool  $skipSquadCheck  Skip squad-full check (used for season-close pre-contracts
     *                                where SquadCapEnforcementProcessor handles trimming)
     */
    private function completeIncomingTransfer(TransferOffer $offer, Game $game, bool $skipSquadCheck = false): bool
    {
        return $this->completionService->completeIncomingTransfer($offer, $game, $skipSquadCheck);
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

    // =========================================
    // SYNCHRONOUS TRANSFER NEGOTIATION
    // =========================================

    /**
     * Synchronous club fee negotiation. Creates or continues a negotiation,
     * evaluates the bid immediately, and returns the result.
     *
     * @return array{result: string, offer: TransferOffer}
     */
    public function negotiateTransferFeeSync(Game $game, GamePlayer $player, int $bidCents, ScoutingService $scoutingService): array
    {
        // Check for existing countered offer to resume
        $existing = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('offering_team_id', $game->team_id)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->whereNotNull('negotiation_round')
            ->where('asking_price', '>', 0)
            ->first();

        if ($existing) {
            // Resume: update bid, increment round
            if ($bidCents > $this->availableBudget($game) + $existing->transfer_fee) {
                throw new \InvalidArgumentException(__('messages.bid_exceeds_budget'));
            }

            $existing->update([
                'transfer_fee' => $bidCents,
                'negotiation_round' => min($existing->negotiation_round + 1, ContractService::MAX_NEGOTIATION_ROUNDS),
            ]);
            $offer = $existing;
        } else {
            // New negotiation: create offer and mark as sync
            if ($bidCents > $this->availableBudget($game)) {
                throw new \InvalidArgumentException(__('messages.bid_exceeds_budget'));
            }

            $existingBid = TransferOffer::where('game_id', $game->id)
                ->where('game_player_id', $player->id)
                ->where('offering_team_id', $game->team_id)
                ->where('status', TransferOffer::STATUS_PENDING)
                ->exists();

            if ($existingBid) {
                throw new \InvalidArgumentException(__('transfers.already_bidding'));
            }

            $wageDemand = $scoutingService->calculateWageDemand($player);

            $offer = TransferOffer::create([
                'game_id' => $game->id,
                'game_player_id' => $player->id,
                'offering_team_id' => $game->team_id,
                'selling_team_id' => $player->team_id,
                'offer_type' => TransferOffer::TYPE_USER_BID,
                'direction' => TransferOffer::DIRECTION_INCOMING,
                'transfer_fee' => $bidCents,
                'offered_wage' => $wageDemand,
                'status' => TransferOffer::STATUS_PENDING,
                'expires_at' => $game->current_date->addDays(30),
                'game_date' => $game->current_date,
                'negotiation_round' => 1,
                'disposition' => $this->calculateClubDisposition($player, $scoutingService),
            ]);
        }

        // Immediately evaluate — pass previous counter as ceiling so the club never raises
        $previousCounter = $existing ? $existing->asking_price : null;
        $evaluation = $scoutingService->evaluateBid($player, $offer->transfer_fee, $game, $previousCounter);

        if ($evaluation['result'] === 'accepted') {
            $offer->update([
                'status' => TransferOffer::STATUS_FEE_AGREED,
                'asking_price' => $evaluation['asking_price'],
                'resolved_at' => $game->current_date,
            ]);
            return ['result' => 'accepted', 'offer' => $offer->fresh()];
        }

        if ($evaluation['result'] === 'counter' && $offer->negotiation_round < ContractService::MAX_NEGOTIATION_ROUNDS) {
            $offer->update([
                'asking_price' => $evaluation['counter_amount'],
            ]);
            return ['result' => 'countered', 'offer' => $offer->fresh()];
        }

        // Rejected (or countered but at max rounds)
        $offer->update([
            'status' => TransferOffer::STATUS_REJECTED,
            'asking_price' => $evaluation['asking_price'],
            'resolved_at' => $game->current_date,
        ]);
        return ['result' => 'rejected', 'offer' => $offer->fresh()];
    }

    /**
     * Accept a club's counter-offer on the transfer fee.
     */
    public function acceptTransferFeeCounter(Game $game, TransferOffer $offer): TransferOffer
    {
        if (!$offer->isPending() || !$offer->isSyncNegotiated() || !$offer->asking_price || $offer->asking_price <= $offer->transfer_fee) {
            throw new \InvalidArgumentException(__('messages.transfer_failed'));
        }

        $counterAmount = $offer->asking_price;
        $available = $this->availableBudget($game) + $offer->transfer_fee;

        if ($counterAmount > $available) {
            throw new \InvalidArgumentException(__('messages.bid_exceeds_budget'));
        }

        $offer->update([
            'transfer_fee' => $counterAmount,
            'status' => TransferOffer::STATUS_FEE_AGREED,
            'resolved_at' => $game->current_date,
        ]);

        return $offer->fresh();
    }

    // =========================================
    // SYNCHRONOUS COUNTER-OFFER NEGOTIATION
    // =========================================

    /**
     * Handle user counter-offering an unsolicited or listed bid.
     *
     * The user demands a higher price for their player. The AI buying club
     * evaluates whether to raise their bid, counter, or walk away.
     *
     * @return array{result: string, offer: TransferOffer}
     */
    public function negotiateCounterOfferSync(Game $game, TransferOffer $offer, int $userAskingCents, ScoutingService $scoutingService): array
    {
        // Increment negotiation round
        $offer->update([
            'asking_price' => $userAskingCents,
            'negotiation_round' => ($offer->negotiation_round ?? 0) + 1,
        ]);

        $evaluation = $scoutingService->evaluateCounterOffer($offer, $userAskingCents, $game);

        if ($evaluation['result'] === 'accepted') {
            $offer->update([
                'transfer_fee' => $userAskingCents,
            ]);
            return ['result' => 'accepted', 'offer' => $offer->fresh()];
        }

        if ($evaluation['result'] === 'countered' && $offer->negotiation_round < ContractService::MAX_NEGOTIATION_ROUNDS) {
            $offer->update([
                'transfer_fee' => $evaluation['counter_amount'],
            ]);
            return ['result' => 'countered', 'offer' => $offer->fresh()];
        }

        // Rejected (or countered but at max rounds)
        $offer->update([
            'status' => TransferOffer::STATUS_REJECTED,
            'resolved_at' => $game->current_date,
        ]);
        return ['result' => 'rejected', 'offer' => $offer->fresh()];
    }

    /**
     * User accepts the AI buyer's latest counter-bid.
     * Completes the sale via the standard acceptOffer flow.
     */
    public function acceptCounterOfferBid(TransferOffer $offer): bool
    {
        return $this->acceptOffer($offer);
    }

    /**
     * Calculate selling club's disposition (willingness to sell).
     * Higher = more willing.
     */
    public function calculateClubDisposition(GamePlayer $player, ScoutingService $scoutingService): float
    {
        $disposition = 0.50;

        // Player importance (key players are harder to buy)
        $importance = $scoutingService->calculatePlayerImportance($player);
        if ($importance >= 0.85) {
            $disposition -= 0.20;
        } elseif ($importance >= 0.60) {
            $disposition -= 0.10;
        } elseif ($importance <= 0.30) {
            $disposition += 0.10;
        }

        // Contract length (longer = more reluctant)
        if ($player->contract_until) {
            $yearsLeft = $player->game->current_date->diffInYears($player->contract_until);
            if ($yearsLeft >= 4) {
                $disposition -= 0.10;
            } elseif ($yearsLeft <= 1) {
                $disposition += 0.15;
            }
        } else {
            $disposition += 0.20; // No contract = very willing
        }

        // Transfer listed = very willing
        if ($player->transfer_status === 'listed') {
            $disposition += 0.20;
        }

        // Age (older = more willing to sell)
        $age = $player->age($player->game->current_date);
        if ($age >= PlayerAge::PRIME_END) {
            $disposition += 0.10;
        } elseif ($age < PlayerAge::YOUNG_END) {
            $disposition -= 0.05;
        }

        return max(0.10, min(0.95, $disposition));
    }

    /**
     * Get mood indicator for club disposition.
     *
     * @return array{label: string, color: string}
     */
    public function getClubMoodIndicator(float $disposition): array
    {
        if ($disposition >= 0.65) {
            return ['label' => __('transfers.mood_willing_sell'), 'color' => 'green'];
        }
        if ($disposition >= 0.40) {
            return ['label' => __('transfers.mood_open_sell'), 'color' => 'amber'];
        }

        return ['label' => __('transfers.mood_reluctant_sell'), 'color' => 'red'];
    }
}
