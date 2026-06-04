<?php

namespace App\Modules\Transfer\Services;

use App\Modules\Player\PlayerAge;
use App\Modules\Squad\Services\SquadMinimumService;
use App\Modules\Squad\Services\SquadNumberService;
use App\Modules\Transfer\Enums\NegotiationScenario;
use App\Modules\Transfer\Exceptions\SquadMinimumException;
use App\Modules\Transfer\Enums\TransferWindowType;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\LoanService;
use App\Modules\Transfer\Services\ScoutingService;
use App\Support\Money;
use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\Loan;
use App\Models\RenewalNegotiation;
use App\Models\ShortlistedPlayer;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Models\TransferListing;
use App\Models\TransferOffer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransferService
{
    public function __construct(
        private readonly LoanService $loanService,
        private readonly TransferCompletionService $completionService,
        private readonly ContractService $contractService,
        private readonly DispositionService $dispositionService,
        private readonly SquadNumberService $squadNumberService,
        private readonly SquadMinimumService $squadMinimumService,
        private readonly SquadNeedService $squadNeedService,
    ) {}

    /**
     * Opening-offer price curve, applied to market value. A single need-driven
     * curve (listed and unsolicited are priced the same): the AI opens with an
     * aggressive lowball when it has little use for the player and only nears
     * market value when it genuinely needs them. The ceiling sits below the
     * counter-offer premium ceiling (ScoutingService::COUNTER_PREMIUM_CEIL) so
     * there is always room to be negotiated upward.
     *   desire 0.0 → 0.70× MV
     *   desire 1.0 → 1.10× MV
     */
    private const OPEN_PRICE_FLOOR = 0.70;
    private const OPEN_PRICE_CEIL = 1.10;

    /** Reproducible ±band variance on the opening price, seeded per pairing. */
    private const OPEN_PRICE_JITTER = 0.03;

    /**
     * Age adjustments for transfer pricing.
     */
    private const AGE_PREMIUM_YOUNG = 1.10;  // Young talent premium
    private const AGE_DECLINE_PENALTY_PER_YEAR = 0.05;  // 5% per year

    /**
     * Offer expiry in days.
     */
    private const LISTED_OFFER_EXPIRY_DAYS = 14;
    private const UNSOLICITED_OFFER_EXPIRY_DAYS = 14;

    /**
     * Per-matchday unsolicited offer chance keyed by player tier (1-5).
     *
     * The curve peaks at tier 3 (€5–20M) and tapers at both extremes to
     * mirror real transfer-market dynamics:
     *
     *   - Tier 5 (world class, €50M+) — only a handful of clubs can afford
     *     them, so unsolicited bids are rare. Elite clubs tend to sign at
     *     most one marquee player a year.
     *   - Tier 3 (good, €5–20M) — hundreds of clubs worldwide can afford
     *     and want these players. This is where the market is most active.
     *   - Tier 1 (low, <€1M) — still some interest from low-budget clubs,
     *     but the pool of buyers willing to spend an offer slot is smaller.
     *
     * The reputation/budget gates in getEligibleBuyersWithSquadValues()
     * continue to enforce realism (e.g. Segunda clubs still can't bid on
     * tier-4+ La Liga starters).
     */
    private const UNSOLICITED_OFFER_CHANCE_BY_TIER = [
        5 => 0.005,  // World class — tiny buyer pool
        4 => 0.012,  // Excellent — uncommon
        3 => 0.025,  // Good — peak market activity
        2 => 0.020,  // Average — active mid-market
        1 => 0.010,  // Low — moderate interest
    ];

    /**
     * Maximum transfer fee as a fraction of the buying team's squad value.
     * Prevents small clubs from making unrealistically large bids.
     */
    private const MAX_FEE_TO_SQUAD_VALUE_RATIO = 0.15;

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
     * List a player for transfer.
     *
     * @throws SquadMinimumException when listing this player would mean the
     *         squad could fall below its composition minimum if the resulting
     *         offer is accepted.
     */
    public function listPlayer(GamePlayer $player): void
    {
        if ($player->isOnLoan()) {
            return;
        }

        $breach = $this->squadMinimumService->validateRemoval(
            $player->game,
            $player,
            $player->team_id,
        );
        if ($breach !== null) {
            throw new SquadMinimumException($breach);
        }

        TransferListing::updateOrCreate(
            ['game_player_id' => $player->id],
            [
                'game_id' => $player->game_id,
                'team_id' => $player->team_id,
                'status' => TransferListing::STATUS_LISTED,
                'listed_at' => $player->game->current_date,
            ],
        );
    }

    /**
     * Remove a player from the transfer list.
     */
    public function unlistPlayer(GamePlayer $player): void
    {
        TransferListing::where('game_player_id', $player->id)->delete();

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
    public function generateOffersForListedPlayers(Game $game, $allPlayersGrouped = null, ?array $buyerPool = null, int $offerChance = 40): Collection
    {
        $offers = collect();

        // Use pre-loaded players if available, otherwise load
        if ($allPlayersGrouped !== null) {
            $teamPlayers = $allPlayersGrouped->get($game->team_id, collect());
            $listedPlayers = $teamPlayers->filter(
                fn ($p) => $p->isTransferListed()
                    && !$p->isLoanedIn($game->team_id)
            );
        } else {
            $listedPlayers = GamePlayer::with('transferOffers')
                ->where('game_id', $game->id)
                ->where('team_id', $game->team_id)
                ->whereHas('transferListing', fn ($q) => $q->where('status', TransferListing::STATUS_LISTED))
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

            if (rand(1, 100) <= $offerChance) {
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

        // Use pre-loaded players if available, otherwise load.
        // Sorted by value DESC so higher-value players get first pick of
        // buyers — once a buyer commits to one player in this call, they're
        // added to $excludedBuyerTeamIds and can't bid on another.
        if ($allPlayersGrouped !== null) {
            $teamPlayers = $allPlayersGrouped->get($game->team_id, collect());
            $eligiblePlayers = $teamPlayers
                ->filter(fn ($p) => !$p->relationLoaded('transferListing') || $p->transferListing === null)
                ->filter(fn ($p) => !$p->isLoanedIn($game->team_id))
                ->sortByDesc('market_value_cents');
        } else {
            $eligiblePlayers = GamePlayer::with('transferOffers')
                ->where('game_id', $game->id)
                ->where('team_id', $game->team_id)
                ->whereDoesntHave('transferListing')
                ->whereDoesntHave('activeLoan')
                ->orderByDesc('market_value_cents')
                ->get();
        }

        // Collect team IDs that already have a pending unsolicited offer for any player on the user's squad
        $excludedBuyerTeamIds = TransferOffer::where('game_id', $game->id)
            ->where('offer_type', TransferOffer::TYPE_UNSOLICITED)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->whereHas('gamePlayer', fn ($q) => $q->where('team_id', $game->team_id))
            ->pluck('offering_team_id')
            ->toArray();

        // Rivals are excluded from unsolicited offers only — see getRivalTeamIds().
        $excludedBuyerTeamIds = array_values(array_unique(array_merge(
            $excludedBuyerTeamIds,
            $this->getRivalTeamIds($game, $buyerPool),
        )));

        foreach ($eligiblePlayers as $player) {
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

            // Tier-scaled random chance for an offer
            $chance = self::UNSOLICITED_OFFER_CHANCE_BY_TIER[$player->tier] ?? 0;
            if ($chance > 0 && mt_rand() / mt_getrandmax() < $chance) {
                ['buyers' => $buyers, 'squadValues' => $squadValues] = $this->getEligibleBuyersWithSquadValues($player, $buyerPool);

                // Exclude teams that already have a pending unsolicited offer for another player on this squad
                $availableBuyers = $buyers->filter(
                    fn ($team) => !in_array($team->id, $excludedBuyerTeamIds)
                );

                if ($availableBuyers->isNotEmpty()) {
                    $buyer = $this->selectWeightedBuyer($availableBuyers, $player, $squadValues);
                    $offer = $this->createOffer(
                        player: $player,
                        offeringTeam: $buyer,
                        offerType: TransferOffer::TYPE_UNSOLICITED,
                    );
                    $offers->push($offer);
                    $excludedBuyerTeamIds[] = $buyer->id;
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
        $agreedPreContracts = TransferOffer::with(['gamePlayer', 'offeringTeam'])
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
        $agreedIncoming = TransferOffer::with(['gamePlayer', 'sellingTeam'])
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

        $pendingOffers = TransferOffer::with(['gamePlayer', 'offeringTeam'])
            ->where('game_id', $game->id)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->where('game_date', '<=', $responseDate)
            ->get();

        $resolvedOffers = collect();

        foreach ($pendingOffers as $offer) {
            $demand = $this->contractService->calculateWageDemand($offer->gamePlayer, NegotiationScenario::PRE_CONTRACT, $offer->offeringTeam);
            $evaluation = $this->dispositionService->evaluatePreContractOffer($offer->gamePlayer, $offer->offered_wage, $demand['wage'], $game->team);

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
     * Accept a transfer offer. Parks the deal as STATUS_AGREED; the player
     * does not change teams until either the next match finalises (open
     * window) or the next window opens (closed window). The intra-window
     * delay solves the "sign during the matchday, use immediately" exploit.
     *
     * @return bool Always false — completion is now deferred to a listener.
     */
    public function acceptOffer(TransferOffer $offer): bool
    {
        $player = $offer->gamePlayer;
        $game = $offer->game;

        // Squad-composition guard: this is the binding moment — once accepted
        // (or marked agreed), the player is committed to leaving. Block here
        // so the resulting transfer can't shrink the roster below minimums,
        // even if the listing itself was OK at the time it was created.
        $breach = $this->squadMinimumService->validateRemoval($game, $player, $player->team_id);
        if ($breach !== null) {
            throw new SquadMinimumException($breach);
        }

        // Reject all other pending offers for this player
        TransferOffer::where('game_player_id', $player->id)
            ->where('id', '!=', $offer->id)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->update(['status' => TransferOffer::STATUS_REJECTED, 'resolved_at' => $game->current_date]);

        $offer->update(['status' => TransferOffer::STATUS_AGREED, 'resolved_at' => $game->current_date]);
        return false;
    }

    /**
     * Complete all agreed transfers (called at transfer window).
     */
    public function completeAgreedTransfers(Game $game): Collection
    {
        // Sorted by game_player_id so the per-player UPDATE locks are
        // acquired in PK order, keeping lock acquisition deterministic
        // across concurrent writers and avoiding cross-session deadlocks.
        //
        // Pre-contracts are excluded here: they are free transfers that take
        // effect when the player's current contract expires (season end), not
        // mid-window. PreContractTransferProcessor completes them at season
        // end via completePreContractTransfers(). Without this guard a poached
        // player would leave the moment the next match is played, while the
        // symmetric incoming case (completeIncomingTransfers, which carries the
        // same filter) correctly waits until summer.
        $agreedOffers = TransferOffer::with(['gamePlayer', 'offeringTeam'])
            ->where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('offer_type', '!=', TransferOffer::TYPE_PRE_CONTRACT)
            ->whereHas('gamePlayer', function ($query) use ($game) {
                $query->where('team_id', $game->team_id);
            })
            ->orderBy('game_player_id')
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
        // The buyer's roster drives how badly it wants this player (desire),
        // which sets the opening price. Loaded for just the selected buyer —
        // offers per tick are few, so this is bounded, not an N+1 over the
        // (foreign-league-inclusive) buyer pool.
        $buyerRoster = GamePlayer::where('game_id', $player->game_id)
            ->where('team_id', $offeringTeam->id)
            ->get(['id', 'team_id', 'position', 'overall_score', 'market_value_cents']);

        $desire = $this->squadNeedService->desireScore($buyerRoster, $player);
        $transferFee = $this->calculateOfferPrice($player, $desire, $offeringTeam->id);
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
     * Opening offer price for a listed or unsolicited approach. A single
     * need-driven curve (both offer types are priced the same): the AI opens
     * with an aggressive lowball when it has little use for the player and only
     * nears market value when it genuinely needs them. Always opens below the
     * counter-offer ceiling (ScoutingService::COUNTER_PREMIUM_*) so there is
     * room to be negotiated upward.
     *
     * @param  float  $desire  0..1 from SquadNeedService::desireScore.
     */
    private function calculateOfferPrice(GamePlayer $player, float $desire, string $buyerTeamId): int
    {
        $baseValue = $player->market_value_cents;

        $multiplier = self::OPEN_PRICE_FLOOR + $desire * (self::OPEN_PRICE_CEIL - self::OPEN_PRICE_FLOOR);

        // Deterministic per-pairing jitter so two similar approaches differ
        // without breaking reproducibility. Seeded from player + buyer + date.
        $seed = $player->id . $buyerTeamId . $player->game->current_date->format('Y-m-d');
        $multiplier += $this->squadNeedService->jitter($seed, self::OPEN_PRICE_JITTER);
        $multiplier = max(self::OPEN_PRICE_FLOOR - self::OPEN_PRICE_JITTER, $multiplier);

        // Age modifier
        $age = $player->age($player->game->current_date);
        $ageModifier = 1.0;

        if ($age < PlayerAge::YOUNG_END) {
            $ageModifier = self::AGE_PREMIUM_YOUNG;
        } elseif ($age > PlayerAge::primePhaseAge(0.5)) {
            $yearsOverMidPrime = $age - PlayerAge::primePhaseAge(0.5);
            $ageModifier = max(0.5, 1.0 - ($yearsOverMidPrime * self::AGE_DECLINE_PENALTY_PER_YEAR));
        }

        $finalPrice = (int) ($baseValue * $multiplier * $ageModifier);

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
     * @return array{leagueTeams: Collection, squadValues: Collection, reputationLevels: \Illuminate\Support\Collection}
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
     * Rivals = teams in the user's same domestic league AND same reputation tier.
     * Real-world equivalent: Real Madrid <-> Barcelona. Such unsolicited moves
     * are vanishingly rare and break immersion when AI-generated. Only unsolicited
     * offers are filtered; listed-player and pre-contract offers remain open
     * because the user either opened the door or the contract is expiring.
     *
     * @param  array{leagueTeams: Collection, squadValues: Collection, reputationLevels: Collection}|null  $buyerPool
     * @return array<int, string>
     */
    private function getRivalTeamIds(Game $game, ?array $buyerPool = null): array
    {
        if (!$game->competition_id) {
            return [];
        }

        $userReputation = TeamReputation::resolveLevel($game->id, $game->team_id);

        $sameLeagueIds = $game->competition
            ->teams()
            ->wherePivot('season', $game->season)
            ->where('teams.id', '!=', $game->team_id)
            ->pluck('teams.id')
            ->all();

        if (empty($sameLeagueIds)) {
            return [];
        }

        $reputationLevels = $buyerPool['reputationLevels']
            ?? TeamReputation::resolveLevels($game->id, $sameLeagueIds);

        return collect($sameLeagueIds)
            ->filter(fn ($id) => ($reputationLevels[$id] ?? ClubProfile::REPUTATION_LOCAL) === $userReputation)
            ->values()
            ->all();
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
     * Invoked both when a window opens (cross-window agreements) and when a
     * match finalises during an open window (intra-window agreements).
     */
    public function completeIncomingTransfers(Game $game): Collection
    {
        // orderBy('game_player_id') keeps the UPDATE locks deterministic
        // across concurrent writers — see completeAgreedTransfers().
        $agreedIncoming = TransferOffer::with(['gamePlayer', 'sellingTeam'])
            ->where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->where('offer_type', '!=', TransferOffer::TYPE_PRE_CONTRACT)
            ->orderBy('game_player_id')
            ->get();

        // Also get loan-out agreements
        $agreedLoanOuts = TransferOffer::with(['gamePlayer', 'offeringTeam'])
            ->where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('offer_type', TransferOffer::TYPE_LOAN_OUT)
            ->orderBy('game_player_id')
            ->get();

        $completedTransfers = collect();

        foreach ($agreedIncoming as $offer) {
            if ($offer->offer_type === TransferOffer::TYPE_LOAN_IN) {
                $this->loanService->completeLoanIn($offer, $game);
            } elseif ($this->isFreeAgentOffer($offer)) {
                // Free-agent signings (no selling club) need the free-agent
                // completion path so the GameTransfer is logged as
                // TYPE_FREE_AGENT and the career record uses ORIGIN_FREE_AGENT.
                $this->completionService->completeFreeAgentSigning($game, $offer->gamePlayer, $offer);
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

    private function isFreeAgentOffer(TransferOffer $offer): bool
    {
        return $offer->offer_type === TransferOffer::TYPE_USER_BID
            && $offer->selling_team_id === null
            && (int) $offer->transfer_fee === 0;
    }

    /**
     * Sign a free agent: park a STATUS_AGREED offer so the player joins
     * from the next matchday after the agreement (see
     * CompleteAgreedTransfersOnMatchPlayed). The player record is not
     * touched here — completion handles team_id, squad number, contract
     * length and the GameTransfer entry.
     */
    public function signFreeAgent(Game $game, GamePlayer $player, int $wageDemand): TransferOffer
    {
        $contractYears = $player->age($game->current_date) >= 32 ? 1 : mt_rand(2, 3);

        $offer = TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $game->team_id,
            'selling_team_id' => null,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'offered_wage' => $wageDemand,
            'offered_years' => $contractYears,
            'status' => TransferOffer::STATUS_AGREED,
            'resolved_at' => $game->current_date,
            'expires_at' => $game->current_date,
            'game_date' => $game->current_date,
        ]);

        return $offer;
    }

    public function acceptIncomingOffer(TransferOffer $offer): bool
    {
        $game = $offer->game;

        // Park as agreed regardless of window state. Completion is handled
        // by CompleteAgreedTransfersOnMatchPlayed (intra-window) or
        // CompleteAgreedTransfersOnWindowOpen (cross-window) so that newly
        // signed players never become available for the matchday they were
        // signed during.
        $offer->update(['status' => TransferOffer::STATUS_AGREED, 'resolved_at' => $game->current_date]);
        return false;
    }

    /**
     * Complete a single incoming transfer (user buys player).
     */
    private function completeIncomingTransfer(TransferOffer $offer, Game $game): bool
    {
        return $this->completionService->completeIncomingTransfer($offer, $game);
    }

    /**
     * Squad-composition guard for the AI selling side. The seller refuses to
     * part with the player when doing so would shrink their roster more than
     * one player short of the normal floors.
     *
     * The leniency is intentional and scoped to this code path: when a third
     * party (the user) tables an attractive bid, the AI accepts a one-player
     * dip below the global SquadMinimumService floor in exchange for the
     * windfall. So the user can raid depth — taking a rival from 2 keepers
     * to 1 — but never strip a position bare. The global rule still binds
     * the seller's own listings, releases, and squad management.
     *
     * Other in-flight commitments from the same club — outgoing offers
     * already at fee_agreed or agreed — are counted as already-gone so the
     * user can't drain a position group by stacking parallel negotiations on
     * several of the seller's players at once (e.g. bidding on every
     * goalkeeper before any single deal completes).
     *
     * @throws \InvalidArgumentException when the sale would breach the
     *         seller's tolerated squad-composition minimum.
     */
    private function assertSellerCanPartWith(GamePlayer $player, Game $game): void
    {
        $sellingTeamId = $player->team_id;
        if ($sellingTeamId === null) {
            return;
        }

        $committedAwayIds = TransferOffer::where('game_id', $game->id)
            ->where('selling_team_id', $sellingTeamId)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->whereIn('offer_type', [
                TransferOffer::TYPE_USER_BID,
                TransferOffer::TYPE_LISTED,
                TransferOffer::TYPE_UNSOLICITED,
            ])
            ->whereIn('status', [
                TransferOffer::STATUS_FEE_AGREED,
                TransferOffer::STATUS_AGREED,
            ])
            ->where('game_player_id', '!=', $player->id)
            ->pluck('game_player_id')
            ->all();

        $remainingSquad = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $sellingTeamId)
            ->when($committedAwayIds, fn ($q) => $q->whereNotIn('id', $committedAwayIds))
            ->get();

        // Seller-side leniency: allow the AI to dip one player short of the
        // normal floors in this code path only.
        $tolerantSquadMin = SquadMinimumService::MIN_SQUAD_SIZE - 1;

        // Selling this player would drop the totals by one
        if (($remainingSquad->count() - 1) < $tolerantSquadMin) {
            throw new \InvalidArgumentException(
                __('transfers.club_refuses_squad_minimum', [
                    'team' => $player->team?->name ?? '',
                    'player' => $player->name,
                ])
            );
        }

        $positionGroup = $player->position_group;
        $groupMinimum = SquadMinimumService::POSITION_GROUP_MINIMUMS[$positionGroup] ?? 0;
        if ($groupMinimum === 0) {
            return;
        }

        $tolerantGroupMin = $groupMinimum - 1;
        $groupCount = $remainingSquad
            ->filter(fn (GamePlayer $p) => $p->position_group === $positionGroup)
            ->count();

        if (($groupCount - 1) < $tolerantGroupMin) {
            throw new \InvalidArgumentException(
                __('transfers.club_refuses_squad_minimum', [
                    'team' => $player->team?->name ?? '',
                    'player' => $player->name,
                ])
            );
        }
    }

    /**
     * Players with an active loan are not available for transfer offers — the
     * parent club retains authority and the loan team isn't free to sell. The
     * UI hides the bid action, but the service guards the same rule so a
     * direct request can't bypass it.
     */
    public function playerHasActiveLoan(GamePlayer $player): bool
    {
        if ($player->relationLoaded('activeLoan')) {
            return $player->activeLoan !== null;
        }

        return Loan::where('game_player_id', $player->id)
            ->where('status', Loan::STATUS_ACTIVE)
            ->exists();
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

        if (!$this->dispositionService->canSignPreContract($player, $game->id, $game->team_id)) {
            throw new \InvalidArgumentException(
                __('transfers.pre_contract_player_not_interested', ['player' => $player->name])
            );
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
     * Trigger a release clause: the user pays the pre-agreed buyout on an
     * AI-owned player. The selling club cannot refuse the fee, so the offer is
     * created directly at STATUS_FEE_AGREED (the fee is forced) — the only thing
     * left is the personal-terms negotiation, which the player may still reject.
     *
     * The fee is escrowed the instant the FEE_AGREED offer exists, because
     * committedBudget() reserves it; affordability is therefore checked against
     * availableBudget(). The personal-terms phase, salary-cap enforcement, the
     * FEE_AGREED → AGREED transition, and completion all reuse the normal
     * transfer pipeline (NegotiateTransfer + AgreedTransferCompletionProcessor),
     * so no clause-specific negotiation or completion code is needed here.
     *
     * @throws \InvalidArgumentException when the trigger is not permitted.
     */
    public function triggerReleaseClause(Game $game, GamePlayer $player): TransferOffer
    {
        if (!$game->release_clauses_enabled) {
            throw new \InvalidArgumentException(__('transfers.clause_not_available'));
        }

        if ($player->isUserOwned($game)) {
            throw new \InvalidArgumentException(__('transfers.cannot_target_own_player'));
        }

        if (!$player->hasReleaseClause()) {
            throw new \InvalidArgumentException(__('transfers.clause_not_available'));
        }

        // Loaned / called-up players can't be clause-triggered in v1: the parent
        // club retains authority and the loan destination can't be forced to sell.
        if ($this->playerHasActiveLoan($player)) {
            throw new \InvalidArgumentException(__('transfers.player_on_loan_unavailable'));
        }

        $clauseCents = (int) $player->release_clause;

        // Serialize the whole check-expire-create sequence so two near-
        // simultaneous triggers (e.g. a double-clicked confirm) can't both pass
        // the affordability guard and create duplicate FEE_AGREED offers /
        // double-escrow. The row lock on the player makes a concurrent trigger
        // block until the first commits; it then expires the first's offer below
        // before creating its own, so only one live offer ever exists.
        return DB::transaction(function () use ($game, $player, $clauseCents) {
            GamePlayer::where('id', $player->id)->lockForUpdate()->first();

            // The fee is reserved via committedBudget() the moment the FEE_AGREED
            // offer exists, so guard affordability against the same pool a normal
            // bid would draw from. Add back any reservation this player already
            // holds (e.g. a prior bid or an earlier clause trigger) — it is
            // expired and replaced below, so it must not count against this buy.
            $existingReservation = (int) TransferOffer::where('game_id', $game->id)
                ->where('game_player_id', $player->id)
                ->where('offering_team_id', $game->team_id)
                ->where('direction', TransferOffer::DIRECTION_INCOMING)
                ->whereIn('offer_type', [TransferOffer::TYPE_USER_BID, TransferOffer::TYPE_LOAN_IN])
                ->whereIn('status', [
                    TransferOffer::STATUS_PENDING,
                    TransferOffer::STATUS_FEE_AGREED,
                    TransferOffer::STATUS_AGREED,
                ])
                ->get()
                ->sum(fn (TransferOffer $o) => $o->committedAmount());

            if ($clauseCents > $this->availableBudget($game) + $existingReservation) {
                throw new \InvalidArgumentException(__('transfers.clause_exceeds_budget'));
            }

            // A release clause is non-refusable: unlike negotiateTransferFeeSync
            // we deliberately skip assertSellerCanPartWith() — once the clause is
            // paid the seller cannot block the sale on squad-minimum grounds.

            // Exclusivity + clean slate: expire any other live offer this club
            // has for the player (e.g. a half-finished normal bid) so the
            // FEE_AGREED clause offer is the single negotiation handleStart()/
            // handleStartTerms resolve — they fetch with ->first() and a
            // duplicate would shadow it.
            TransferOffer::where('game_id', $game->id)
                ->where('game_player_id', $player->id)
                ->where('offering_team_id', $game->team_id)
                ->whereIn('status', [
                    TransferOffer::STATUS_PENDING,
                    TransferOffer::STATUS_FEE_AGREED,
                    TransferOffer::STATUS_AGREED,
                ])
                ->update(['status' => TransferOffer::STATUS_EXPIRED, 'resolved_at' => $game->current_date]);

            // Defensive: an AI player won't carry a user renewal negotiation, but
            // cancel one if it somehow exists so no stale negotiation lingers.
            $activeRenewal = $player->activeRenewalNegotiation;
            if ($activeRenewal) {
                $activeRenewal->update(['status' => RenewalNegotiation::STATUS_CLUB_DECLINED]);
            }

            // Bootstrap the personal-terms phase from the player's wage demand,
            // exactly like the sync-bid builder, so the negotiation chat opens
            // cleanly into terms negotiation.
            $transferDemand = $this->contractService->calculateWageDemand($player, NegotiationScenario::TRANSFER, $game->team);

            return TransferOffer::create([
                'game_id' => $game->id,
                'game_player_id' => $player->id,
                'offering_team_id' => $game->team_id,
                'selling_team_id' => $player->team_id,
                'offer_type' => TransferOffer::TYPE_USER_BID,
                'direction' => TransferOffer::DIRECTION_INCOMING,
                'transfer_fee' => $clauseCents,
                'offered_wage' => $transferDemand['wage'],
                'offered_years' => $transferDemand['contractYears'],
                'status' => TransferOffer::STATUS_FEE_AGREED,
                'triggered_release_clause' => true,
                'expires_at' => $game->current_date->addDays(30),
                'game_date' => $game->current_date,
                'negotiation_round' => 1,
                'resolved_at' => $game->current_date,
            ]);
        });
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
        if ($player->isUserOwned($game)) {
            throw new \InvalidArgumentException(__('transfers.cannot_target_own_player'));
        }

        if ($this->playerHasActiveLoan($player)) {
            throw new \InvalidArgumentException(__('transfers.player_on_loan_unavailable'));
        }

        $this->assertSellerCanPartWith($player, $game);

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
            if ($bidCents > $this->availableBudget($game) + $existing->committedAmount()) {
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

            $transferDemand = $this->contractService->calculateWageDemand($player, NegotiationScenario::TRANSFER, $game->team);

            $offer = TransferOffer::create([
                'game_id' => $game->id,
                'game_player_id' => $player->id,
                'offering_team_id' => $game->team_id,
                'selling_team_id' => $player->team_id,
                'offer_type' => TransferOffer::TYPE_USER_BID,
                'direction' => TransferOffer::DIRECTION_INCOMING,
                'transfer_fee' => $bidCents,
                'offered_wage' => $transferDemand['wage'],
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

        // The release clause caps the whole fee negotiation: a club never asks or
        // counters above the buyout, so the displayed/stored ask stays reachable
        // under the slider cap (a bid that meets the clause force-buys upstream).
        if ($game->release_clauses_enabled && $player->hasReleaseClause()) {
            $clauseCents = (int) $player->release_clause;
            if (isset($evaluation['asking_price'])) {
                $evaluation['asking_price'] = min((int) $evaluation['asking_price'], $clauseCents);
            }
            if (isset($evaluation['counter_amount'])) {
                $evaluation['counter_amount'] = min((int) $evaluation['counter_amount'], $clauseCents);
            }
        }

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
        $available = $this->availableBudget($game) + $offer->committedAmount();

        if ($counterAmount > $available) {
            throw new \InvalidArgumentException(__('messages.bid_exceeds_budget'));
        }

        // Re-check at the binding moment: another parallel negotiation could
        // have driven the seller below their minimum since this counter was
        // tabled, in which case they can no longer commit to selling.
        $this->assertSellerCanPartWith($offer->gamePlayer, $game);

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
        return $this->dispositionService->clubSellDisposition($player);
    }

    /**
     * Get mood indicator for club disposition.
     *
     * @return array{label: string, color: string}
     */
    public function getClubMoodIndicator(float $disposition): array
    {
        return $this->dispositionService->moodIndicator($disposition, 'transfer_sell');
    }
}
