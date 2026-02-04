<?php

namespace App\Game\Services;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Support\Money;
use Illuminate\Support\Collection;

class ContractService
{
    /**
     * Default minimum wage if no competition found (€100K in cents).
     */
    private const DEFAULT_MINIMUM_WAGE = 10_000_000;

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
        // Find the team's primary league competition
        $league = Competition::whereHas('teams', function ($query) use ($team) {
            $query->where('teams.id', $team->id);
        })
            ->where('type', 'league')
            ->first();

        return $league?->minimum_annual_wage ?? self::DEFAULT_MINIMUM_WAGE;
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

        return $competition?->minimum_annual_wage ?? self::DEFAULT_MINIMUM_WAGE;
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
        if (!$player->canBeOfferedRenewal()) {
            return false;
        }

        $game = $player->game;
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
        return GamePlayer::with('player')
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->get()
            ->filter(fn ($player) => $player->canBeOfferedRenewal())
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
}
