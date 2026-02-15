<?php

namespace App\Game\Services;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\RenewalNegotiation;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Support\Money;
use Illuminate\Support\Collection;

class ContractService
{
    /**
     * Minimum annual wages by competition tier (in cents).
     * Based on Spanish labor regulations for professional football.
     */
    private const MINIMUM_WAGES = [
        1 => 20_000_000, // €200K - La Liga
        2 => 10_000_000, // €100K - La Liga 2
    ];

    private const DEFAULT_MINIMUM_WAGE = 10_000_000; // €100K

    private array $minimumWageCache = [];

    /**
     * Wage percentage tiers based on market value.
     * Higher value players command a larger percentage of their value as wages.
     */
    private const WAGE_TIERS = [
        ['min_value' => 10_000_000_000, 'percentage' => 0.175], // €100M+ → 17.5%
        ['min_value' => 5_000_000_000, 'percentage' => 0.15],   // €50-100M → 15%
        ['min_value' => 2_000_000_000, 'percentage' => 0.125],  // €20-50M → 12.5%
        ['min_value' => 1_000_000_000, 'percentage' => 0.11],   // €10-20M → 11%
        ['min_value' => 500_000_000, 'percentage' => 0.10],     // €5-10M → 10%
        ['min_value' => 200_000_000, 'percentage' => 0.09],     // €2-5M → 9%
        ['min_value' => 0, 'percentage' => 0.08],               // <€2M → 8%
    ];

    /**
     * Age-based wage modifiers.
     *
     * Young players: Signed rookie contracts with no leverage - underpaid relative to value.
     * Prime players: Fair market contracts.
     * Veterans: Legacy contracts from peak years - overpaid relative to current value.
     */
    private const AGE_WAGE_MODIFIERS = [
        17 => 0.40,  // First pro contract, minimal leverage
        18 => 0.50,
        19 => 0.60,
        20 => 0.70,
        21 => 0.80,
        22 => 0.90,
        // 23-29: 1.0 (fair market)
        30 => 1.30,  // Starting to be "overpaid" relative to declining value
        31 => 1.60,
        32 => 2.00,
        33 => 2.50,
        34 => 3.00,
        35 => 4.00,  // Significant legacy premium
        36 => 5.00,
        37 => 6.00,
        38 => 7.00,  // Legends like Modric
    ];

    /**
     * Calculate annual wage for a player based on market value and age.
     *
     * The age modifier accounts for contract dynamics:
     * - Young players have rookie contracts (discount)
     * - Veterans have legacy contracts from their prime (premium)
     *
     * Includes ±10% variance and enforces league minimum wage.
     *
     * @param int $marketValueCents Player's market value in cents
     * @param int $minimumWageCents League minimum wage in cents
     * @param int|null $age Player's age (null defaults to prime-age calculation)
     * @return int Annual wage in cents
     */
    public function calculateAnnualWage(int $marketValueCents, int $minimumWageCents, ?int $age = null): int
    {
        // Get wage percentage based on market value tier
        $percentage = $this->getWagePercentage($marketValueCents);

        // Calculate base wage from market value
        $baseWage = (int) ($marketValueCents * $percentage);

        // Apply age-based modifier
        $ageModifier = $this->getAgeWageModifier($age);
        $baseWage = (int) ($baseWage * $ageModifier);

        // Apply ±10% variance for squad diversity
        $variance = 0.90 + (mt_rand(0, 2000) / 10000); // 0.90 to 1.10
        $wage = (int) ($baseWage * $variance);

        // Enforce minimum wage
        return max($wage, $minimumWageCents);
    }

    /**
     * Get age-based wage modifier.
     *
     * @param int|null $age
     * @return float Multiplier (0.4 for 17yo rookies to 7.0 for 38yo legends)
     */
    private function getAgeWageModifier(?int $age): float
    {
        if ($age === null) {
            return 1.0; // Default to prime-age modifier
        }

        // Check exact age match
        if (isset(self::AGE_WAGE_MODIFIERS[$age])) {
            return self::AGE_WAGE_MODIFIERS[$age];
        }

        // Young players under 17: use 17's modifier
        if ($age < 17) {
            return self::AGE_WAGE_MODIFIERS[17];
        }

        // Prime years (23-29): fair market
        if ($age >= 23 && $age <= 29) {
            return 1.0;
        }

        // Very old players (39+): use 38's modifier
        if ($age > 38) {
            return self::AGE_WAGE_MODIFIERS[38];
        }

        return 1.0; // Fallback
    }

    /**
     * Get wage percentage tier based on market value.
     */
    private function getWagePercentage(int $marketValueCents): float
    {
        foreach (self::WAGE_TIERS as $tier) {
            if ($marketValueCents >= $tier['min_value']) {
                return $tier['percentage'];
            }
        }

        return 0.08; // Default fallback
    }

    /**
     * Get the minimum annual wage for a team based on their primary league.
     *
     * @param Team $team
     * @return int Minimum wage in cents
     */
    public function getMinimumWageForTeam(Team $team): int
    {
        if (isset($this->minimumWageCache[$team->id])) {
            return $this->minimumWageCache[$team->id];
        }

        $league = Competition::whereHas('teams', function ($query) use ($team) {
            $query->where('teams.id', $team->id);
        })
            ->where('role', Competition::ROLE_PRIMARY)
            ->first();

        return $this->minimumWageCache[$team->id] = self::MINIMUM_WAGES[$league?->tier] ?? self::DEFAULT_MINIMUM_WAGE;
    }

    /**
     * Get the minimum annual wage for a competition.
     *
     * @param string $competitionId
     * @return int Minimum wage in cents
     */
    public function getMinimumWageForCompetition(string $competitionId): int
    {
        $competition = Competition::find($competitionId);

        return self::MINIMUM_WAGES[$competition?->tier] ?? self::DEFAULT_MINIMUM_WAGE;
    }

    /**
     * Calculate total annual wage bill for a game's squad.
     *
     * @param Game $game
     * @return int Total annual wages in cents
     */
    public function calculateAnnualWageBill(Game $game): int
    {
        return GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->sum('annual_wage');
    }

    /**
     * Calculate monthly wage bill for a game's squad.
     *
     * @param Game $game
     * @return int Monthly wages in cents
     */
    public function calculateMonthlyWageBill(Game $game): int
    {
        return (int) ($this->calculateAnnualWageBill($game) / 12);
    }

    /**
     * Get highest paid players in a squad.
     *
     * @param Game $game
     * @param int $limit
     * @return Collection<GamePlayer>
     */
    public function getHighestEarners(Game $game, int $limit = 5): Collection
    {
        return GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->orderByDesc('annual_wage')
            ->limit($limit)
            ->get();
    }

    /**
     * Get players with contracts expiring in a given year.
     *
     * @param Game $game
     * @param int $year
     * @return Collection<GamePlayer>
     */
    public function getExpiringContracts(Game $game, int $year): Collection
    {
        return GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereYear('contract_until', $year)
            ->orderBy('annual_wage', 'desc')
            ->get();
    }

    /**
     * Get contracts expiring at end of current season.
     *
     * @param Game $game
     * @return Collection<GamePlayer>
     */
    public function getContractsExpiringThisSeason(Game $game): Collection
    {
        // Assume season ends in June of the season year
        $seasonYear = (int) $game->season;

        return $this->getExpiringContracts($game, $seasonYear);
    }

    /**
     * Group squad contracts by expiry year.
     *
     * @param Game $game
     * @return Collection<int, Collection<GamePlayer>>
     */
    public function getContractsByExpiryYear(Game $game): Collection
    {
        return GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereNotNull('contract_until')
            ->orderBy('contract_until')
            ->get()
            ->groupBy(fn ($player) => $player->contract_until->year);
    }

    // =========================================
    // CONTRACT RENEWAL
    // =========================================

    /**
     * Renewal premium: players want a raise to renew.
     */
    private const RENEWAL_PREMIUM = 1.15; // 15% raise

    /**
     * Default renewal contract length in years.
     */
    private const DEFAULT_RENEWAL_YEARS = 3;

    /**
     * Calculate what wage a player demands for a contract renewal.
     * Players expect a raise over their current wage, based on market value.
     *
     * @param GamePlayer $player
     * @return array{wage: int, contractYears: int, formattedWage: string}
     */
    public function calculateRenewalDemand(GamePlayer $player): array
    {
        $minimumWage = $this->getMinimumWageForTeam($player->team);

        // Calculate fair market wage based on current market value
        $marketWage = $this->calculateAnnualWage(
            $player->market_value_cents,
            $minimumWage,
            $player->age
        );

        // Player wants the higher of: current wage + premium, or market wage
        $currentWageWithPremium = (int) ($player->annual_wage * self::RENEWAL_PREMIUM);
        $demandedWage = max($currentWageWithPremium, $marketWage);

        // Round to nearest 100K (cents)
        $demandedWage = (int) (round($demandedWage / 10_000_000) * 10_000_000);

        // Contract length based on age
        $contractYears = $this->calculateRenewalYears($player->age);

        return [
            'wage' => $demandedWage,
            'contractYears' => $contractYears,
            'formattedWage' => Money::format($demandedWage),
        ];
    }

    /**
     * Calculate how many years a player will sign for based on age.
     */
    private function calculateRenewalYears(int $age): int
    {
        if ($age >= 33) {
            return 1; // Veterans get 1-year deals
        }
        if ($age >= 30) {
            return 2; // 30-32 get 2-year deals
        }

        return self::DEFAULT_RENEWAL_YEARS; // Under 30 get 3-year deals
    }

    /**
     * Process a contract renewal offer.
     * Updates contract end date immediately, stores pending wage for end of season.
     *
     * @param GamePlayer $player
     * @param int $newWage The agreed wage (in cents)
     * @param int $contractYears How many years to extend
     * @return bool Success
     */
    public function processRenewal(GamePlayer $player, int $newWage, int $contractYears): bool
    {
        $game = $player->game;
        $seasonEndDate = $game->getSeasonEndDate();

        if (!$player->canBeOfferedRenewal($seasonEndDate)) {
            return false;
        }

        $seasonYear = (int) $game->season;

        // New contract ends in June of (current season + contract years)
        $newContractEnd = \Carbon\Carbon::createFromDate($seasonYear + $contractYears + 1, 6, 30);

        $player->update([
            'contract_until' => $newContractEnd,
            'pending_annual_wage' => $newWage,
        ]);

        return true;
    }

    /**
     * Apply pending wage increases (called at end of season).
     * Returns array of players whose wages were updated.
     *
     * @param Game $game
     * @return Collection<GamePlayer>
     */
    public function applyPendingWages(Game $game): Collection
    {
        $players = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereNotNull('pending_annual_wage')
            ->get();

        foreach ($players as $player) {
            $player->update([
                'annual_wage' => $player->pending_annual_wage,
                'pending_annual_wage' => null,
            ]);
        }

        return $players;
    }

    /**
     * Get players eligible for contract renewal.
     *
     * @param Game $game
     * @return Collection<GamePlayer>
     */
    public function getPlayersEligibleForRenewal(Game $game): Collection
    {
        $seasonEndDate = $game->getSeasonEndDate();

        return GamePlayer::with(['player', 'team', 'game', 'transferOffers', 'latestRenewalNegotiation', 'activeRenewalNegotiation'])
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->get()
            ->filter(fn ($player) => $player->canBeOfferedRenewal($seasonEndDate))
            ->sortBy('contract_until');
    }

    /**
     * Get players with pending renewals (wage increase at end of season).
     *
     * @param Game $game
     * @return Collection<GamePlayer>
     */
    public function getPlayersWithPendingRenewals(Game $game): Collection
    {
        return GamePlayer::with('player')
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereNotNull('pending_annual_wage')
            ->orderByDesc('pending_annual_wage')
            ->get();
    }

    // =========================================
    // CONTRACT NEGOTIATION
    // =========================================

    private const MAX_NEGOTIATION_ROUNDS = 3;

    /**
     * Calculate a player's disposition score (0.10 – 0.95).
     * Higher = more willing to accept a lower wage.
     */
    public function calculateDisposition(GamePlayer $player, int $round = 1): float
    {
        $disposition = 0.50;

        // Morale bonus
        $morale = $player->morale;
        if ($morale >= 80) {
            $disposition += 0.15;
        } elseif ($morale >= 60) {
            $disposition += 0.08;
        } elseif ($morale < 40) {
            $disposition -= 0.10;
        }

        // Appearances bonus
        $appearances = $player->season_appearances ?? $player->appearances ?? 0;
        if ($appearances >= 25) {
            $disposition += 0.10;
        } elseif ($appearances >= 15) {
            $disposition += 0.05;
        } elseif ($appearances < 10) {
            $disposition -= 0.10;
        }

        // Age factor
        $age = $player->age;
        if ($age >= 32) {
            $disposition += 0.12;
        } elseif ($age >= 29) {
            $disposition += 0.05;
        } elseif ($age <= 23) {
            $disposition -= 0.08;
        }

        // Round penalty
        if ($round === 2) {
            $disposition -= 0.05;
        } elseif ($round >= 3) {
            $disposition -= 0.10;
        }

        // Pre-contract pressure (Jan-May)
        $game = $player->game;
        if ($game) {
            $month = $game->current_date->month;
            if ($month >= 1 && $month <= 5) {
                // Has a concrete pre-contract offer
                if ($player->relationLoaded('transferOffers')) {
                    $hasPreContractOffer = $player->transferOffers->contains(function ($offer) {
                        return $offer->offer_type === TransferOffer::TYPE_PRE_CONTRACT
                            && $offer->status === TransferOffer::STATUS_PENDING;
                    });
                } else {
                    $hasPreContractOffer = $player->transferOffers()
                        ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
                        ->where('status', TransferOffer::STATUS_PENDING)
                        ->exists();
                }

                if ($hasPreContractOffer) {
                    $disposition -= 0.15;
                } else {
                    $disposition -= 0.08;
                }
            }
        }

        return max(0.10, min(0.95, $disposition));
    }

    /**
     * Get the mood label and color for a disposition score.
     *
     * @return array{label: string, color: string}
     */
    public function getMoodIndicator(float $disposition): array
    {
        if ($disposition >= 0.65) {
            return ['label' => __('transfers.mood_willing'), 'color' => 'green'];
        }
        if ($disposition >= 0.40) {
            return ['label' => __('transfers.mood_open'), 'color' => 'amber'];
        }

        return ['label' => __('transfers.mood_reluctant'), 'color' => 'red'];
    }

    /**
     * Calculate the years modifier based on offered vs preferred years.
     */
    private function calculateYearsModifier(int $offeredYears, int $preferredYears): float
    {
        $diff = $offeredYears - $preferredYears;

        return match ($diff) {
            0 => 1.00,
            1 => 1.08,
            2 => 1.15,
            -1 => 0.90,
            -2 => 0.80,
            default => $diff > 0 ? 1.15 : 0.80,
        };
    }

    /**
     * Initiate a new renewal negotiation.
     */
    public function initiateNegotiation(GamePlayer $player, int $offerWage, int $offeredYears): RenewalNegotiation
    {
        $demand = $this->calculateRenewalDemand($player);

        // Check for any previous negotiations (for round carry-over)
        $previousNegotiation = RenewalNegotiation::where('game_player_id', $player->id)
            ->whereIn('status', [
                RenewalNegotiation::STATUS_PLAYER_REJECTED,
                RenewalNegotiation::STATUS_CLUB_DECLINED,
                RenewalNegotiation::STATUS_CLUB_RECONSIDERED,
                RenewalNegotiation::STATUS_EXPIRED,
            ])
            ->orderByDesc('round')
            ->first();

        $startRound = $previousNegotiation ? min($previousNegotiation->round + 1, self::MAX_NEGOTIATION_ROUNDS) : 1;

        return RenewalNegotiation::create([
            'game_id' => $player->game_id,
            'game_player_id' => $player->id,
            'status' => RenewalNegotiation::STATUS_OFFER_PENDING,
            'round' => $startRound,
            'player_demand' => $demand['wage'],
            'preferred_years' => $demand['contractYears'],
            'user_offer' => $offerWage,
            'offered_years' => $offeredYears,
        ]);
    }

    /**
     * Evaluate a pending negotiation offer. Called during matchday advance.
     *
     * @return string 'accepted' | 'countered' | 'rejected'
     */
    public function evaluateOffer(RenewalNegotiation $negotiation): string
    {
        $player = $negotiation->gamePlayer;
        $disposition = $this->calculateDisposition($player, $negotiation->round);
        $negotiation->update(['disposition' => $disposition]);

        // Calculate minimum acceptable wage
        $flexibility = $disposition * 0.30;
        $minimumAcceptable = (int) ($negotiation->player_demand * (1.0 - $flexibility));

        // Apply years modifier to effective offer
        $yearsModifier = $this->calculateYearsModifier($negotiation->offered_years, $negotiation->preferred_years);
        $effectiveOffer = (int) ($negotiation->user_offer * $yearsModifier);

        if ($effectiveOffer >= $minimumAcceptable) {
            // Accept
            $contractYears = $negotiation->offered_years;
            $negotiation->update([
                'status' => RenewalNegotiation::STATUS_ACCEPTED,
                'contract_years' => $contractYears,
            ]);

            // Process the actual renewal
            $this->processRenewal($player, $negotiation->user_offer, $contractYears);

            return 'accepted';
        }

        // Check if close enough for a counter (>= 85% of minimum)
        $counterThreshold = (int) ($minimumAcceptable * 0.85);

        if ($effectiveOffer >= $counterThreshold && $negotiation->round < self::MAX_NEGOTIATION_ROUNDS) {
            // Counter — offer midpoint between minimum and demand, using player's preferred years
            $counterWage = (int) (($minimumAcceptable + $negotiation->player_demand) / 2);
            // Round to nearest 100K
            $counterWage = (int) (round($counterWage / 10_000_000) * 10_000_000);

            $negotiation->update([
                'status' => RenewalNegotiation::STATUS_PLAYER_COUNTERED,
                'counter_offer' => $counterWage,
            ]);

            return 'countered';
        }

        // Reject
        $negotiation->update(['status' => RenewalNegotiation::STATUS_PLAYER_REJECTED]);

        return 'rejected';
    }

    /**
     * Accept a counter-offer from the player (instant resolution).
     */
    public function acceptCounterOffer(RenewalNegotiation $negotiation): bool
    {
        if (!$negotiation->isCountered()) {
            return false;
        }

        $player = $negotiation->gamePlayer;
        $contractYears = $negotiation->preferred_years;

        $negotiation->update([
            'status' => RenewalNegotiation::STATUS_ACCEPTED,
            'contract_years' => $contractYears,
        ]);

        $this->processRenewal($player, $negotiation->counter_offer, $contractYears);

        return true;
    }

    /**
     * Submit a new offer in response to a counter (next round).
     */
    public function submitNewOffer(RenewalNegotiation $negotiation, int $newOfferWage, int $offeredYears): RenewalNegotiation
    {
        if (!$negotiation->isCountered()) {
            return $negotiation;
        }

        $nextRound = $negotiation->round + 1;

        $negotiation->update([
            'status' => RenewalNegotiation::STATUS_OFFER_PENDING,
            'round' => $nextRound,
            'user_offer' => $newOfferWage,
            'offered_years' => $offeredYears,
            'counter_offer' => null,
        ]);

        return $negotiation;
    }

    /**
     * Cancel an active negotiation (user walks away).
     */
    public function cancelNegotiation(RenewalNegotiation $negotiation): void
    {
        $negotiation->update(['status' => RenewalNegotiation::STATUS_CLUB_DECLINED]);
    }

    /**
     * Decline renewal without negotiating (user says "No renovar").
     * Creates a club_declined record so the decision is tracked.
     */
    public function declineWithoutNegotiation(GamePlayer $player): RenewalNegotiation
    {
        return RenewalNegotiation::create([
            'game_id' => $player->game_id,
            'game_player_id' => $player->id,
            'status' => RenewalNegotiation::STATUS_CLUB_DECLINED,
            'round' => 0,
        ]);
    }

    /**
     * Reconsider a previously declined/rejected renewal.
     * Marks the blocking record as club_reconsidered so the player becomes eligible again.
     */
    public function reconsiderRenewal(GamePlayer $player): void
    {
        $latest = $player->relationLoaded('latestRenewalNegotiation')
            ? $player->latestRenewalNegotiation
            : $player->latestRenewalNegotiation()->first();

        if ($latest && $latest->isBlocking()) {
            $latest->update(['status' => RenewalNegotiation::STATUS_CLUB_RECONSIDERED]);
        }
    }

    /**
     * Resolve all pending negotiations for a game. Called during matchday advance.
     *
     * @return Collection<array{negotiation: RenewalNegotiation, result: string}>
     */
    public function resolveRenewalNegotiations(Game $game): Collection
    {
        $pending = RenewalNegotiation::with(['gamePlayer.player', 'gamePlayer.game'])
            ->where('game_id', $game->id)
            ->where('status', RenewalNegotiation::STATUS_OFFER_PENDING)
            ->get();

        $results = collect();

        foreach ($pending as $negotiation) {
            $result = $this->evaluateOffer($negotiation);
            $results->push([
                'negotiation' => $negotiation->fresh(),
                'result' => $result,
            ]);
        }

        return $results;
    }

    /**
     * Get active negotiations for a game (pending or countered).
     */
    public function getActiveNegotiations(Game $game): Collection
    {
        return RenewalNegotiation::with(['gamePlayer.player'])
            ->where('game_id', $game->id)
            ->whereIn('status', [RenewalNegotiation::STATUS_OFFER_PENDING, RenewalNegotiation::STATUS_PLAYER_COUNTERED])
            ->get()
            ->keyBy('game_player_id');
    }

    /**
     * Get players currently in negotiation (for display in expiring contracts).
     */
    public function getPlayersInNegotiation(Game $game): Collection
    {
        return GamePlayer::with(['player', 'game', 'transferOffers', 'activeRenewalNegotiation'])
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereHas('activeRenewalNegotiation')
            ->get();
    }

    /**
     * Clean up stale negotiations (e.g., at season end).
     */
    public function expireStaleNegotiations(Game $game): int
    {
        $stale = RenewalNegotiation::where('game_id', $game->id)
            ->whereIn('status', [RenewalNegotiation::STATUS_OFFER_PENDING, RenewalNegotiation::STATUS_PLAYER_COUNTERED])
            ->get();

        foreach ($stale as $negotiation) {
            $negotiation->update(['status' => RenewalNegotiation::STATUS_EXPIRED]);
        }

        return $stale->count();
    }
}
