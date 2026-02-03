<?php

namespace App\Game\Services;

use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GamePlayer;
use App\Models\GameStanding;

class FinancialService
{
    /**
     * TV revenue tiers based on squad market value (in cents).
     * Higher value squads = bigger clubs = more TV money.
     */
    private const TV_REVENUE_TIERS = [
        ['min_squad_value' => 80_000_000_000, 'tv_revenue' => 14_000_000_000],  // €800M+ squad → €140M TV
        ['min_squad_value' => 60_000_000_000, 'tv_revenue' => 11_000_000_000],  // €600M+ squad → €110M TV
        ['min_squad_value' => 40_000_000_000, 'tv_revenue' => 9_000_000_000],   // €400M+ squad → €90M TV
        ['min_squad_value' => 20_000_000_000, 'tv_revenue' => 6_000_000_000],   // €200M+ squad → €60M TV
        ['min_squad_value' => 10_000_000_000, 'tv_revenue' => 4_000_000_000],   // €100M+ squad → €40M TV
        ['min_squad_value' => 5_000_000_000, 'tv_revenue' => 2_500_000_000],    // €50M+ squad → €25M TV
        ['min_squad_value' => 0, 'tv_revenue' => 1_500_000_000],                 // <€50M squad → €15M TV
    ];

    /**
     * La Liga 2 TV revenue tiers (much lower).
     */
    private const TV_REVENUE_TIERS_TIER2 = [
        ['min_squad_value' => 20_000_000_000, 'tv_revenue' => 1_500_000_000],   // €200M+ squad → €15M TV
        ['min_squad_value' => 10_000_000_000, 'tv_revenue' => 1_000_000_000],   // €100M+ squad → €10M TV
        ['min_squad_value' => 5_000_000_000, 'tv_revenue' => 700_000_000],      // €50M+ squad → €7M TV
        ['min_squad_value' => 0, 'tv_revenue' => 500_000_000],                   // <€50M squad → €5M TV
    ];

    /**
     * Performance bonus per position (1st = €15M down to 20th = €0).
     * In cents.
     */
    private const MAX_PERFORMANCE_BONUS = 1_500_000_000; // €15M

    /**
     * Cup round bonuses (Copa del Rey).
     */
    private const CUP_BONUSES = [
        'Round of 64' => 10_000_000,       // €100K
        'Round of 32' => 25_000_000,       // €250K
        'Round of 16' => 50_000_000,       // €500K
        'Quarter-finals' => 100_000_000,   // €1M
        'Semi-finals' => 200_000_000,      // €2M
        'Final' => 500_000_000,            // €5M
        'Winner' => 1_000_000_000,         // €10M (additional)
    ];

    /**
     * Initialize finances for a new game.
     */
    public function initializeFinances(Game $game): GameFinances
    {
        $squadValue = $this->calculateSquadValue($game);
        $tvRevenue = $this->calculateTvRevenue($squadValue, $this->getLeagueTier($game));
        $wageBill = $this->calculateAnnualWageBill($game);

        // Initial balance: 20% of TV revenue as starting capital
        $initialBalance = (int) ($tvRevenue * 0.20);

        // Wage budget: 70% of expected TV revenue
        $wageBudget = (int) ($tvRevenue * 0.70);

        // Transfer budget: 10% of TV revenue + (wage budget - actual wage bill)
        $wageHeadroom = max(0, $wageBudget - $wageBill);
        $transferBudget = (int) ($tvRevenue * 0.10) + (int) ($wageHeadroom * 0.5);

        return GameFinances::create([
            'game_id' => $game->id,
            'balance' => $initialBalance,
            'wage_budget' => $wageBudget,
            'transfer_budget' => $transferBudget,
            'tv_revenue' => $tvRevenue,
        ]);
    }

    /**
     * Calculate end of season financials.
     */
    public function calculateSeasonEnd(Game $game): GameFinances
    {
        $finances = $game->finances;

        if (!$finances) {
            $finances = $this->initializeFinances($game);
        }

        // Calculate revenues
        $squadValue = $this->calculateSquadValue($game);
        $leagueTier = $this->getLeagueTier($game);
        $tvRevenue = $this->calculateTvRevenue($squadValue, $leagueTier);
        $performanceBonus = $this->calculatePerformanceBonus($game);
        $cupBonus = $this->calculateCupBonus($game);
        $totalRevenue = $tvRevenue + $performanceBonus + $cupBonus;

        // Calculate expenses
        $wageExpense = $this->calculateAnnualWageBill($game);
        $transferExpense = $finances->transfer_expense; // Already tracked during season
        $totalExpense = $wageExpense + $transferExpense;

        // Calculate profit/loss
        $profitLoss = $totalRevenue - $totalExpense;
        $newBalance = $finances->balance + $profitLoss;

        $finances->update([
            'tv_revenue' => $tvRevenue,
            'performance_bonus' => $performanceBonus,
            'cup_bonus' => $cupBonus,
            'total_revenue' => $totalRevenue,
            'wage_expense' => $wageExpense,
            'total_expense' => $totalExpense,
            'season_profit_loss' => $profitLoss,
            'balance' => $newBalance,
        ]);

        return $finances->fresh();
    }

    /**
     * Reset finances for new season while carrying over balance.
     */
    public function prepareNewSeason(Game $game): GameFinances
    {
        $finances = $game->finances;
        $currentBalance = $finances?->balance ?? 0;

        $squadValue = $this->calculateSquadValue($game);
        $tvRevenue = $this->calculateTvRevenue($squadValue, $this->getLeagueTier($game));
        $wageBill = $this->calculateAnnualWageBill($game);

        // Wage budget: 70% of expected TV revenue
        $wageBudget = (int) ($tvRevenue * 0.70);

        // Transfer budget: 10% of TV revenue + wage headroom + portion of positive balance
        $wageHeadroom = max(0, $wageBudget - $wageBill);
        $balanceBonus = $currentBalance > 0 ? (int) ($currentBalance * 0.3) : 0;
        $transferBudget = (int) ($tvRevenue * 0.10) + (int) ($wageHeadroom * 0.5) + $balanceBonus;

        if ($finances) {
            $finances->update([
                'wage_budget' => $wageBudget,
                'transfer_budget' => $transferBudget,
                'tv_revenue' => 0,
                'performance_bonus' => 0,
                'cup_bonus' => 0,
                'total_revenue' => 0,
                'wage_expense' => 0,
                'transfer_expense' => 0,
                'total_expense' => 0,
                'season_profit_loss' => 0,
            ]);
            return $finances->fresh();
        }

        return GameFinances::create([
            'game_id' => $game->id,
            'balance' => $currentBalance,
            'wage_budget' => $wageBudget,
            'transfer_budget' => $transferBudget,
        ]);
    }

    /**
     * Calculate total squad market value.
     */
    public function calculateSquadValue(Game $game): int
    {
        return GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->sum('market_value_cents');
    }

    /**
     * Calculate annual wage bill.
     */
    public function calculateAnnualWageBill(Game $game): int
    {
        return GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->sum('annual_wage');
    }

    /**
     * Calculate TV revenue based on squad value and league tier.
     */
    public function calculateTvRevenue(int $squadValue, int $leagueTier): int
    {
        $tiers = $leagueTier === 1 ? self::TV_REVENUE_TIERS : self::TV_REVENUE_TIERS_TIER2;

        foreach ($tiers as $tier) {
            if ($squadValue >= $tier['min_squad_value']) {
                return $tier['tv_revenue'];
            }
        }

        return $tiers[array_key_last($tiers)]['tv_revenue'];
    }

    /**
     * Calculate performance bonus based on league position.
     * 1st place = €15M, 20th place = €0, linear interpolation.
     */
    public function calculatePerformanceBonus(Game $game): int
    {
        $standing = GameStanding::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();

        if (!$standing) {
            return 0;
        }

        // Get total teams in the league
        $totalTeams = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $standing->competition_id)
            ->count();

        if ($totalTeams <= 1) {
            return self::MAX_PERFORMANCE_BONUS;
        }

        $position = $standing->position;

        // Linear: 1st = max bonus, last = 0
        $bonusPerPosition = self::MAX_PERFORMANCE_BONUS / ($totalTeams - 1);
        $bonus = (int) (self::MAX_PERFORMANCE_BONUS - ($position - 1) * $bonusPerPosition);

        return max(0, $bonus);
    }

    /**
     * Calculate cup bonus based on furthest round reached.
     */
    public function calculateCupBonus(Game $game): int
    {
        // For now, a simple implementation based on cup_round
        // This can be expanded to track actual cup progress
        $bonus = 0;

        if ($game->cup_round >= 1) {
            $bonus += self::CUP_BONUSES['Round of 64'] ?? 0;
        }
        if ($game->cup_round >= 2) {
            $bonus += self::CUP_BONUSES['Round of 32'] ?? 0;
        }
        if ($game->cup_round >= 3) {
            $bonus += self::CUP_BONUSES['Round of 16'] ?? 0;
        }
        if ($game->cup_round >= 4) {
            $bonus += self::CUP_BONUSES['Quarter-finals'] ?? 0;
        }
        if ($game->cup_round >= 5) {
            $bonus += self::CUP_BONUSES['Semi-finals'] ?? 0;
        }
        if ($game->cup_round >= 6) {
            $bonus += self::CUP_BONUSES['Final'] ?? 0;
        }

        return $bonus;
    }

    /**
     * Get the league tier for a game (1 = top division, 2 = second division).
     */
    private function getLeagueTier(Game $game): int
    {
        // Get the team's primary league
        $league = $game->team->competitions()
            ->where('type', 'league')
            ->first();

        return $league?->tier ?? 1;
    }

    /**
     * Format a money value for display.
     */
    public static function formatMoney(int $cents): string
    {
        return ContractService::formatWage($cents);
    }
}
