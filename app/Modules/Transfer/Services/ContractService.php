<?php

namespace App\Modules\Transfer\Services;

use App\Models\Competition;
use App\Modules\Player\PlayerAge;
use App\Models\FinancialTransaction;
use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\TeamReputation;
use App\Models\RenewalNegotiation;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Modules\Notification\Services\NotificationService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * Age-based wage modifiers by PlayerAge tier.
     *
     * Academy: Rookie contracts with no leverage - underpaid relative to value.
     * Young: Developing players, still below market rate.
     * Prime: Fair market contracts.
     * Veteran: Legacy contracts from peak years - overpaid relative to current value.
     */
    private const AGE_WAGE_MODIFIERS = [
        'academy' => 0.60,
        'young' => 0.85,
        'prime' => 1.0,
        'veteran' => 5.0,
    ];

    private const FLEXIBILITY_RATIO = 0.18;
    private const AMBITION_PENALTY_PER_TIER_GAP = 0.12;

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
    public function calculateAnnualWage(int $marketValueCents, int $minimumWageCents, ?int $age = null, bool $deterministic = false): int
    {
        // Get wage percentage based on market value tier
        $percentage = $this->getWagePercentage($marketValueCents);

        // Calculate base wage from market value
        $baseWage = (int) ($marketValueCents * $percentage);

        // Apply age-based modifier
        $ageModifier = $this->getAgeWageModifier($age);
        $baseWage = (int) ($baseWage * $ageModifier);

        if (!$deterministic) {
            // Apply ±10% variance for squad diversity
            $variance = 0.90 + (mt_rand(0, 2000) / 10000); // 0.90 to 1.10
            $baseWage = (int) ($baseWage * $variance);
        }

        // Round to nearest €10k (1_000_000 cents)
        $wage = (int) (round($baseWage / 1_000_000) * 1_000_000);

        // Enforce minimum wage
        return max($wage, $minimumWageCents);
    }

    /**
     * Get age-based wage modifier using PlayerAge tiers.
     *
     * @return float Multiplier (0.60 for academy to 5.0 for veterans)
     */
    private function getAgeWageModifier(?int $age): float
    {
        if ($age === null) {
            return self::AGE_WAGE_MODIFIERS['prime'];
        }

        $tier = match (true) {
            $age <= PlayerAge::ACADEMY_END => 'academy',
            $age <= PlayerAge::YOUNG_END => 'young',
            $age <= PlayerAge::PRIME_END => 'prime',
            default => 'veteran',
        };

        return self::AGE_WAGE_MODIFIERS[$tier];
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
    public function getDefaultMinimumWage(): int
    {
        return self::DEFAULT_MINIMUM_WAGE;
    }

    public function getMinimumWageForTeam(Team $team): int
    {
        if (isset($this->minimumWageCache[$team->id])) {
            return $this->minimumWageCache[$team->id];
        }

        $league = Competition::whereHas('teams', function ($query) use ($team) {
            $query->where('teams.id', $team->id);
        })
            ->where('role', Competition::ROLE_LEAGUE)
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
            $player->age($player->game->current_date)
        );

        // Player wants the higher of: current wage + premium, or market wage
        $currentWageWithPremium = (int) ($player->annual_wage * self::RENEWAL_PREMIUM);
        $demandedWage = max($currentWageWithPremium, $marketWage);

        $demandedWage = $this->roundWage($demandedWage);

        // Ensure the demand is at least above the current wage
        if ($demandedWage <= $player->annual_wage) {
            $unit = $demandedWage < 100_000_000 ? 1_000_000 : 10_000_000;
            $demandedWage = $player->annual_wage + $unit;
        }

        // Contract length based on age
        $contractYears = $this->calculateRenewalYears($player->age($player->game->current_date));

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
        return match (true) {
            $age >= PlayerAge::PRIME_END => 1,
            $age < PlayerAge::YOUNG_END => 5,
            default => self::DEFAULT_RENEWAL_YEARS,
        };
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
        // Fetch affected players first (for return value / metadata)
        $players = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereNotNull('pending_annual_wage')
            ->get();

        // Single bulk update: copy pending_annual_wage → annual_wage, then clear
        if ($players->isNotEmpty()) {
            GamePlayer::where('game_id', $game->id)
                ->where('team_id', $game->team_id)
                ->whereNotNull('pending_annual_wage')
                ->update([
                    'annual_wage' => DB::raw('pending_annual_wage'),
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

    public const MAX_NEGOTIATION_ROUNDS = 3;

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
        $age = $player->age($player->game->current_date);
        if ($age >= PlayerAge::PRIME_END) {
            $disposition += 0.12;
        } elseif ($age >= PlayerAge::primePhaseAge(0.5)) {
            $disposition += 0.05;
        } elseif ($age <= PlayerAge::YOUNG_END) {
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

        // Ambition: players too good for their team's reputation want to move up
        $reputationLevel = TeamReputation::resolveLevel($player->game_id, $player->team_id);
        $teamReputationIndex = ClubProfile::getReputationTierIndex($reputationLevel); // 0-4
        $playerTierIndex = $player->tier - 1; // normalize to 0-4

        $tierGap = $playerTierIndex - $teamReputationIndex;
        if ($tierGap > 0) {
            $disposition -= $tierGap * self::AMBITION_PENALTY_PER_TIER_GAP;
        }

        return max(0.10, min(0.95, $disposition));
    }

    /**
     * Get the mood label and color for a disposition score.
     *
     * @return array{label: string, color: string}
     */
    public function getMoodIndicator(float $disposition, string $context = 'renewal'): array
    {
        if ($context === 'transfer') {
            if ($disposition >= 0.65) {
                return ['label' => __('transfers.mood_willing_sign'), 'color' => 'green'];
            }
            if ($disposition >= 0.40) {
                return ['label' => __('transfers.mood_open_sign'), 'color' => 'amber'];
            }

            return ['label' => __('transfers.mood_reluctant_sign'), 'color' => 'red'];
        }

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
     * Adaptive wage rounding: €10K for wages under €1M, €100K otherwise.
     */
    private function roundWage(int $wageCents): int
    {
        $unit = $wageCents < 100_000_000 ? 1_000_000 : 10_000_000;

        return (int) (round($wageCents / $unit) * $unit);
    }

    /**
     * Round a counter-offer wage, ensuring it never drops below minimumAcceptable.
     */
    private function roundCounterOffer(int $wage, int $minimumAcceptable): int
    {
        $rounded = $this->roundWage($wage);

        if ($rounded < $minimumAcceptable) {
            $unit = $wage < 100_000_000 ? 1_000_000 : 10_000_000;
            $rounded = (int) (ceil($wage / $unit) * $unit);
        }

        return $rounded;
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
     * Evaluate a pending negotiation offer. Called synchronously during chat negotiation.
     *
     * @return string 'accepted' | 'countered' | 'rejected'
     */
    public function evaluateOffer(RenewalNegotiation $negotiation): string
    {
        $player = $negotiation->gamePlayer;
        $disposition = $this->calculateDisposition($player, $negotiation->round);

        // Calculate minimum acceptable wage
        $flexibility = $disposition * self::FLEXIBILITY_RATIO;
        $minimumAcceptable = (int) ($negotiation->player_demand * (1.0 - $flexibility));

        // Salary floor: players don't take pay cuts
        // Exception: veterans with high morale value stability over money
        $age = $player->age($player->game->current_date);
        if (!($age >= PlayerAge::PRIME_END && $player->morale >= 70)) {
            $minimumAcceptable = max($minimumAcceptable, $player->annual_wage);
        }

        // Apply years modifier to effective offer
        $yearsModifier = $this->calculateYearsModifier($negotiation->offered_years, $negotiation->preferred_years);
        $effectiveOffer = (int) ($negotiation->user_offer * $yearsModifier);

        $updateData = ['disposition' => $disposition];

        if ($effectiveOffer >= $minimumAcceptable) {
            $contractYears = $negotiation->offered_years;
            $updateData['status'] = RenewalNegotiation::STATUS_ACCEPTED;
            $updateData['contract_years'] = $contractYears;

            $negotiation->fill($updateData)->save();
            $this->processRenewal($player, $negotiation->user_offer, $contractYears);

            return 'accepted';
        }

        // Check if close enough for a counter (>= 85% of minimum)
        $counterThreshold = (int) ($minimumAcceptable * 0.85);

        if ($effectiveOffer >= $counterThreshold && $negotiation->round < self::MAX_NEGOTIATION_ROUNDS) {
            $counterWage = (int) (($minimumAcceptable + $negotiation->player_demand) / 2);
            $counterWage = $this->roundCounterOffer($counterWage, $minimumAcceptable);

            // Never raise above previous counter
            if ($negotiation->counter_offer !== null && $counterWage > $negotiation->counter_offer) {
                $counterWage = $negotiation->counter_offer;
            }

            $updateData['status'] = RenewalNegotiation::STATUS_PLAYER_COUNTERED;
            $updateData['counter_offer'] = $counterWage;

            $negotiation->fill($updateData)->save();

            return 'countered';
        }

        $updateData['status'] = RenewalNegotiation::STATUS_PLAYER_REJECTED;
        $negotiation->fill($updateData)->save();

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
        /** @var RenewalNegotiation|null $latest */
        $latest = $player->relationLoaded('latestRenewalNegotiation')
            ? $player->latestRenewalNegotiation
            : $player->latestRenewalNegotiation()->first();

        if ($latest && $latest->isBlocking()) {
            $latest->update(['status' => RenewalNegotiation::STATUS_CLUB_RECONSIDERED]);
        }
    }

    /**
     * Synchronous negotiation: initiate (or continue) and evaluate in one call.
     * Used by the chat-based negotiation UI.
     *
     * @return array{result: string, negotiation: RenewalNegotiation}
     */
    public function negotiateSync(GamePlayer $player, int $offerWage, int $offeredYears): array
    {
        // Check if continuing from a counter-offer
        $existing = RenewalNegotiation::where('game_player_id', $player->id)
            ->where('status', RenewalNegotiation::STATUS_PLAYER_COUNTERED)
            ->first();

        if ($existing) {
            $negotiation = $this->submitNewOffer($existing, $offerWage, $offeredYears);
        } else {
            $negotiation = $this->initiateNegotiation($player, $offerWage, $offeredYears);
        }

        // Immediately evaluate (instead of waiting for matchday)
        $result = $this->evaluateOffer($negotiation);

        return [
            'result' => $result,
            'negotiation' => $negotiation,
        ];
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
        return RenewalNegotiation::where('game_id', $game->id)
            ->whereIn('status', [RenewalNegotiation::STATUS_OFFER_PENDING, RenewalNegotiation::STATUS_PLAYER_COUNTERED])
            ->update(['status' => RenewalNegotiation::STATUS_EXPIRED]);
    }

    // =========================================
    // PLAYER RELEASE (CONTRACT TERMINATION)
    // =========================================

    /**
     * Severance rate: fraction of remaining contract wages paid as compensation.
     */
    private const SEVERANCE_RATE = 0.50;

    /**
     * Minimum squad size — cannot release if it would drop below this.
     */
    public const MIN_SQUAD_SIZE = 20;

    /**
     * Maximum squad size — cannot add players above this.
     */
    public const MAX_SQUAD_SIZE = 30;

    /**
     * Count first-team players in the user's squad.
     */
    public static function squadCount(Game $game): int
    {
        return GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->count();
    }

    /**
     * Check if the user's squad is at or above the maximum size.
     */
    public static function isSquadFull(Game $game): bool
    {
        return self::squadCount($game) >= self::MAX_SQUAD_SIZE;
    }

    /**
     * Clear the squad_trim pending action if the squad is now within the cap.
     */
    public static function clearSquadTrimIfResolved(Game $game): void
    {
        if ($game->hasPendingAction('squad_trim') && self::squadCount($game) <= self::MAX_SQUAD_SIZE) {
            $game->removePendingAction('squad_trim');
        }
    }

    /**
     * Minimum players per position group — mirrors SquadReplenishmentProcessor.
     */
    private const POSITION_GROUP_MINIMUMS = [
        'Goalkeeper' => 2,
        'Defender' => 5,
        'Midfielder' => 5,
        'Forward' => 3,
    ];

    /**
     * Release a player from the user's squad (unilateral contract termination).
     *
     * The player becomes a free agent (team_id = null) and the club pays
     * severance equal to 50% of remaining contract wages.
     *
     * @return array{error?: string, playerName?: string, severance?: int, formattedSeverance?: string}
     */
    public function releasePlayer(Game $game, GamePlayer $player): array
    {
        $playerName = $player->name;

        // Eligibility checks
        if ($error = $this->validateRelease($game, $player)) {
            return ['error' => $error];
        }

        // Calculate severance
        $severance = $this->calculateSeverance($game, $player);

        // Record severance as a financial transaction
        if ($severance > 0) {
            FinancialTransaction::recordExpense(
                gameId: $game->id,
                category: FinancialTransaction::CATEGORY_SEVERANCE,
                amount: $severance,
                description: __('finances.tx_player_released', ['player' => $playerName]),
                transactionDate: $game->current_date,
                relatedPlayerId: $player->id,
            );
        }

        // Release the player to the free agent pool
        $player->update([
            'team_id' => null,
            'number' => null,
            'transfer_status' => null,
            'transfer_listed_at' => null,
        ]);

        // Cancel any active renewal negotiations
        $activeNegotiation = $player->activeRenewalNegotiation;
        if ($activeNegotiation) {
            $activeNegotiation->update(['status' => RenewalNegotiation::STATUS_EXPIRED]);
        }

        self::clearSquadTrimIfResolved($game);

        // Send notification
        app(NotificationService::class)->notifyPlayerReleased(
            $game,
            $playerName,
            $severance,
        );

        return [
            'playerName' => $playerName,
            'severance' => $severance,
            'formattedSeverance' => Money::format($severance),
        ];
    }

    /**
     * Validate whether a player can be released.
     *
     * @return string|null Error message, or null if valid
     */
    private function validateRelease(Game $game, GamePlayer $player): ?string
    {
        // Must belong to the user's team
        if ($player->team_id !== $game->team_id) {
            return __('messages.release_not_your_player');
        }

        // Cannot release players on loan
        if ($player->isLoanedOut($game->team_id) || $player->isLoanedIn($game->team_id)) {
            return __('messages.release_on_loan');
        }

        // Cannot release players with agreed transfers
        if ($player->hasAgreedTransfer()) {
            return __('messages.release_has_agreed_transfer');
        }

        // Cannot release players with pre-contract agreements
        if ($player->hasPreContractAgreement()) {
            return __('messages.release_has_pre_contract');
        }

        // Squad size check
        $currentSquadSize = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->count();

        if ($currentSquadSize <= self::MIN_SQUAD_SIZE) {
            return __('messages.release_squad_too_small', ['min' => self::MIN_SQUAD_SIZE]);
        }

        // Position group minimum check
        $positionGroup = $player->position_group;
        $groupMinimum = self::POSITION_GROUP_MINIMUMS[$positionGroup] ?? 0;

        if ($groupMinimum > 0) {
            $groupCount = GamePlayer::where('game_id', $game->id)
                ->where('team_id', $game->team_id)
                ->get()
                ->filter(fn ($p) => $p->position_group === $positionGroup)
                ->count();

            if ($groupCount <= $groupMinimum) {
                return __('messages.release_position_minimum', [
                    'group' => __('squad.' . strtolower($positionGroup) . 's'),
                    'min' => $groupMinimum,
                ]);
            }
        }

        return null;
    }

    /**
     * Calculate severance pay for releasing a player.
     *
     * Severance = remaining contract years x annual wage x severance rate (50%).
     */
    public function calculateSeverance(Game $game, GamePlayer $player): int
    {
        if (!$player->contract_until || !$player->annual_wage) {
            return 0;
        }

        $remainingYears = max(0, $game->current_date->floatDiffInYears($player->contract_until));

        return (int) ($player->annual_wage * $remainingYears * self::SEVERANCE_RATE);
    }

    // =========================================
    // TRANSFER PERSONAL TERMS NEGOTIATION
    // =========================================

    /**
     * Calculate a player's disposition for a transfer (willingness to join).
     * Different from renewal — considers team attractiveness, not appearances.
     */
    public function calculateTransferDisposition(GamePlayer $player, Game $buyingClubGame, int $round = 1): float
    {
        $disposition = 0.50;

        // Morale (content player = open to moves)
        $morale = $player->morale;
        if ($morale >= 70) {
            $disposition += 0.10;
        } elseif ($morale < 40) {
            $disposition -= 0.05;
        }

        // Age (older = wants a good final contract)
        $age = $player->age($player->game->current_date);
        if ($age >= PlayerAge::PRIME_END) {
            $disposition += 0.10;
        } elseif ($age <= PlayerAge::YOUNG_END) {
            $disposition -= 0.05;
        }

        // Team reputation comparison
        $buyingRep = $buyingClubGame->team?->reputation ?? 50;
        $currentRep = $player->team?->reputation ?? 50;
        if ($buyingRep > $currentRep + 10) {
            $disposition += 0.15;
        } elseif ($buyingRep >= $currentRep - 10) {
            $disposition += 0.05;
        } else {
            $disposition -= 0.10;
        }

        // Round penalty
        if ($round === 2) {
            $disposition -= 0.05;
        } elseif ($round >= 3) {
            $disposition -= 0.10;
        }

        return max(0.10, min(0.95, $disposition));
    }

    /**
     * Calculate the wage demand for a transfer (what player wants to join).
     *
     * @return array{wage: int, contractYears: int, formattedWage: string}
     */
    public function calculateTransferWageDemand(GamePlayer $player, ScoutingService $scoutingService): array
    {
        $wage = $scoutingService->calculateWageDemand($player);
        $age = $player->age($player->game->current_date);

        $contractYears = $age >= PlayerAge::PRIME_END ? 1 : ($age >= PlayerAge::primePhaseAge(0.6) ? 2 : 3);

        return [
            'wage' => $wage,
            'contractYears' => $contractYears,
            'formattedWage' => Money::format($wage),
        ];
    }

    /**
     * Check if a player is willing to negotiate personal terms.
     * Rejects the offer if the player refuses based on club reputation gap.
     *
     * @return array{willing: bool, offer: TransferOffer}
     */
    public function checkPlayerWillingness(TransferOffer $offer, Game $buyingClubGame, ScoutingService $scoutingService): array
    {
        $player = $offer->gamePlayer;
        $reputationModifier = $scoutingService->calculateReputationModifier($buyingClubGame->team, $player);

        if ($reputationModifier < 1.0 && rand(1, 100) > (int) ($reputationModifier * 100)) {
            $offer->update([
                'terms_status' => 'rejected',
                'status' => TransferOffer::STATUS_REJECTED,
                'resolved_at' => $buyingClubGame->current_date,
            ]);

            return ['willing' => false, 'offer' => $offer->fresh()];
        }

        return ['willing' => true, 'offer' => $offer];
    }

    /**
     * Synchronous personal terms negotiation for transfers.
     * Initiates or continues negotiation, evaluates immediately.
     *
     * @return array{result: string, offer: TransferOffer}
     */
    public function negotiateTransferTermsSync(TransferOffer $offer, int $offerWageCents, int $offeredYears, Game $buyingClubGame, ScoutingService $scoutingService): array
    {
        $player = $offer->gamePlayer;

        if ($offer->terms_status === 'countered') {
            // Continue from counter
            $offer->update([
                'terms_round' => min(($offer->terms_round ?? 1) + 1, self::MAX_NEGOTIATION_ROUNDS),
                'offered_wage' => $offerWageCents,
                'offered_years' => $offeredYears,
                'wage_counter_offer' => null,
            ]);
        } else {
            // New terms negotiation
            $demand = $this->calculateTransferWageDemand($player, $scoutingService);
            $offer->update([
                'terms_status' => 'pending',
                'terms_round' => 1,
                'player_demand' => $demand['wage'],
                'preferred_years' => $demand['contractYears'],
                'offered_wage' => $offerWageCents,
                'offered_years' => $offeredYears,
            ]);
        }

        // Evaluate
        $disposition = $this->calculateTransferDisposition($player, $buyingClubGame, $offer->terms_round);
        $offer->update(['terms_disposition' => $disposition]);

        $flexibility = $disposition * 0.30;
        $minimumAcceptable = (int) ($offer->player_demand * (1.0 - $flexibility));

        $yearsModifier = $this->calculateYearsModifier($offer->offered_years, $offer->preferred_years);
        $effectiveOffer = (int) ($offer->offered_wage * $yearsModifier);

        if ($effectiveOffer >= $minimumAcceptable) {
            $offer->update([
                'terms_status' => 'accepted',
            ]);
            return ['result' => 'accepted', 'offer' => $offer->fresh()];
        }

        // Check if close enough for counter
        $counterThreshold = (int) ($minimumAcceptable * 0.85);

        if ($effectiveOffer >= $counterThreshold && $offer->terms_round < self::MAX_NEGOTIATION_ROUNDS) {
            $counterWage = (int) (($minimumAcceptable + $offer->player_demand) / 2);
            $counterWage = $this->roundCounterOffer($counterWage, $minimumAcceptable);

            // Never raise above previous counter
            if ($offer->wage_counter_offer !== null && $counterWage > $offer->wage_counter_offer) {
                $counterWage = $offer->wage_counter_offer;
            }

            $offer->update([
                'terms_status' => 'countered',
                'wage_counter_offer' => $counterWage,
            ]);
            return ['result' => 'countered', 'offer' => $offer->fresh()];
        }

        // Rejected
        $offer->update([
            'terms_status' => 'rejected',
            'status' => TransferOffer::STATUS_REJECTED,
            'resolved_at' => $offer->game->current_date,
        ]);
        return ['result' => 'rejected', 'offer' => $offer->fresh()];
    }

    /**
     * Accept the player's counter-offer on personal terms.
     */
    public function acceptTransferTermsCounter(TransferOffer $offer): TransferOffer
    {
        if ($offer->terms_status !== 'countered') {
            throw new \InvalidArgumentException(__('messages.transfer_failed'));
        }

        $offer->update([
            'offered_wage' => $offer->wage_counter_offer,
            'offered_years' => $offer->preferred_years,
            'terms_status' => 'accepted',
        ]);

        return $offer->fresh();
    }

    // =========================================
    // PRE-CONTRACT PERSONAL TERMS NEGOTIATION
    // =========================================

    /**
     * Calculate a player's disposition for a pre-contract (willingness to sign).
     * Higher base than transfer — player is running out of contract, more motivated.
     */
    public function calculatePreContractDisposition(GamePlayer $player, Game $buyingClubGame, int $round = 1, ?ScoutingService $scoutingService = null): float
    {
        $disposition = 0.60;

        // Morale
        $morale = $player->morale;
        if ($morale >= 70) {
            $disposition += 0.10;
        } elseif ($morale < 40) {
            $disposition -= 0.05;
        }

        // Age (older = wants security)
        $age = $player->age($player->game->current_date);
        if ($age >= 32) {
            $disposition += 0.12;
        } elseif ($age <= 23) {
            $disposition -= 0.05;
        }

        // Round penalty
        if ($round === 2) {
            $disposition -= 0.05;
        } elseif ($round >= 3) {
            $disposition -= 0.10;
        }

        // Apply reputation gap modifier (same scale the scout willingness uses)
        if ($scoutingService && $buyingClubGame->team) {
            $reputationModifier = $scoutingService->calculateReputationModifier($buyingClubGame->team, $player);
            $disposition *= $reputationModifier;
        }

        return max(0.10, min(0.95, $disposition));
    }

    /**
     * Calculate the wage demand for a pre-contract signing.
     *
     * @return array{wage: int, contractYears: int, formattedWage: string}
     */
    public function calculatePreContractWageDemand(GamePlayer $player, ScoutingService $scoutingService): array
    {
        $wage = $scoutingService->calculatePreContractWageDemand($player);
        $age = $player->age($player->game->current_date);

        $contractYears = $age >= 33 ? 1 : ($age >= 30 ? 2 : 3);

        return [
            'wage' => $wage,
            'contractYears' => $contractYears,
            'formattedWage' => Money::format($wage),
        ];
    }

    /**
     * Synchronous personal terms negotiation for pre-contracts.
     *
     * @return array{result: string, offer: TransferOffer}
     */
    public function negotiatePreContractTermsSync(TransferOffer $offer, int $offerWageCents, int $offeredYears, Game $buyingClubGame, ScoutingService $scoutingService): array
    {
        $player = $offer->gamePlayer;

        if ($offer->terms_status === 'countered') {
            // Continue from counter
            $offer->update([
                'terms_round' => min(($offer->terms_round ?? 1) + 1, self::MAX_NEGOTIATION_ROUNDS),
                'offered_wage' => $offerWageCents,
                'offered_years' => $offeredYears,
                'wage_counter_offer' => null,
            ]);
        } else {
            // New terms negotiation
            $demand = $this->calculatePreContractWageDemand($player, $scoutingService);
            $offer->update([
                'terms_status' => 'pending',
                'terms_round' => 1,
                'player_demand' => $demand['wage'],
                'preferred_years' => $demand['contractYears'],
                'offered_wage' => $offerWageCents,
                'offered_years' => $offeredYears,
            ]);
        }

        // Evaluate
        $disposition = $this->calculatePreContractDisposition($player, $buyingClubGame, $offer->terms_round, $scoutingService);
        $offer->update(['terms_disposition' => $disposition]);

        $flexibility = $disposition * 0.30;
        $minimumAcceptable = (int) ($offer->player_demand * (1.0 - $flexibility));

        $yearsModifier = $this->calculateYearsModifier($offer->offered_years, $offer->preferred_years);
        $effectiveOffer = (int) ($offer->offered_wage * $yearsModifier);

        if ($effectiveOffer >= $minimumAcceptable) {
            $offer->update([
                'terms_status' => 'accepted',
                'status' => TransferOffer::STATUS_AGREED,
                'resolved_at' => $offer->game->current_date,
            ]);
            return ['result' => 'accepted', 'offer' => $offer->fresh()];
        }

        // Check if close enough for counter
        $counterThreshold = (int) ($minimumAcceptable * 0.85);

        if ($effectiveOffer >= $counterThreshold && $offer->terms_round < self::MAX_NEGOTIATION_ROUNDS) {
            $counterWage = (int) (($minimumAcceptable + $offer->player_demand) / 2);
            $counterWage = $this->roundCounterOffer($counterWage, $minimumAcceptable);

            $offer->update([
                'terms_status' => 'countered',
                'wage_counter_offer' => $counterWage,
            ]);
            return ['result' => 'countered', 'offer' => $offer->fresh()];
        }

        // Rejected
        $offer->update([
            'terms_status' => 'rejected',
            'status' => TransferOffer::STATUS_REJECTED,
            'resolved_at' => $offer->game->current_date,
        ]);
        return ['result' => 'rejected', 'offer' => $offer->fresh()];
    }

    /**
     * Accept the player's counter-offer on pre-contract personal terms.
     */
    public function acceptPreContractTermsCounter(TransferOffer $offer): TransferOffer
    {
        if ($offer->terms_status !== 'countered') {
            throw new \InvalidArgumentException(__('messages.transfer_failed'));
        }

        $offer->update([
            'offered_wage' => $offer->wage_counter_offer,
            'offered_years' => $offer->preferred_years,
            'terms_status' => 'accepted',
            'status' => TransferOffer::STATUS_AGREED,
            'resolved_at' => $offer->game->current_date,
        ]);

        return $offer->fresh();
    }

    // ── Free Agent Negotiation ──

    /**
     * Calculate a free agent's wage demand and preferred contract years.
     */
    public function calculateFreeAgentWageDemand(GamePlayer $player, ScoutingService $scoutingService): array
    {
        $wage = $scoutingService->calculateWageDemand($player);
        $age = $player->age($player->game->current_date);
        $contractYears = $age >= 32 ? 1 : 3;

        return [
            'wage' => $wage,
            'formattedWage' => Money::format($wage),
            'contractYears' => $contractYears,
        ];
    }

    /**
     * Negotiate personal terms with a free agent (synchronous, round-by-round).
     *
     * @return array{result: 'accepted'|'countered'|'rejected', offer: TransferOffer}
     */
    public function negotiateFreeAgentTermsSync(TransferOffer $offer, int $offerWageCents, int $offeredYears, Game $game, ScoutingService $scoutingService): array
    {
        $player = $offer->gamePlayer;

        if ($offer->terms_status === 'countered') {
            $offer->update([
                'terms_round' => min(($offer->terms_round ?? 1) + 1, self::MAX_NEGOTIATION_ROUNDS),
                'offered_wage' => $offerWageCents,
                'offered_years' => $offeredYears,
                'wage_counter_offer' => null,
            ]);
        } else {
            $demand = $this->calculateFreeAgentWageDemand($player, $scoutingService);
            $offer->update([
                'terms_status' => 'pending',
                'terms_round' => 1,
                'player_demand' => $demand['wage'],
                'preferred_years' => $demand['contractYears'],
                'offered_wage' => $offerWageCents,
                'offered_years' => $offeredYears,
            ]);
        }

        // Evaluate using willingness as disposition
        $willingness = $scoutingService->calculateWillingness($player, $game);
        $disposition = $willingness['score'] / 100.0;
        $offer->update(['terms_disposition' => $disposition]);

        $flexibility = $disposition * 0.30;
        $minimumAcceptable = (int) ($offer->player_demand * (1.0 - $flexibility));

        $yearsModifier = $this->calculateYearsModifier($offeredYears, $offer->preferred_years);
        $effectiveOffer = (int) ($offerWageCents * $yearsModifier);

        if ($effectiveOffer >= $minimumAcceptable) {
            $offer->update([
                'terms_status' => 'accepted',
                'status' => TransferOffer::STATUS_COMPLETED,
                'resolved_at' => $game->current_date,
            ]);
            return ['result' => 'accepted', 'offer' => $offer->fresh()];
        }

        $counterThreshold = (int) ($minimumAcceptable * 0.85);

        if ($effectiveOffer >= $counterThreshold && $offer->terms_round < self::MAX_NEGOTIATION_ROUNDS) {
            $counterWage = (int) (($minimumAcceptable + $offer->player_demand) / 2);
            $counterWage = $this->roundCounterOffer($counterWage, $minimumAcceptable);

            $offer->update([
                'terms_status' => 'countered',
                'wage_counter_offer' => $counterWage,
            ]);
            return ['result' => 'countered', 'offer' => $offer->fresh()];
        }

        $offer->update([
            'terms_status' => 'rejected',
            'status' => TransferOffer::STATUS_REJECTED,
            'resolved_at' => $game->current_date,
        ]);
        return ['result' => 'rejected', 'offer' => $offer->fresh()];
    }

    /**
     * Accept the free agent's counter-offer on personal terms.
     */
    public function acceptFreeAgentTermsCounter(TransferOffer $offer): TransferOffer
    {
        if ($offer->terms_status !== 'countered') {
            throw new \InvalidArgumentException(__('messages.transfer_failed'));
        }

        $offer->update([
            'offered_wage' => $offer->wage_counter_offer,
            'offered_years' => $offer->preferred_years,
            'terms_status' => 'accepted',
            'status' => TransferOffer::STATUS_COMPLETED,
            'resolved_at' => $offer->game->current_date,
        ]);

        return $offer->fresh();
    }
}
