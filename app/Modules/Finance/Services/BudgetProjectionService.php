<?php

namespace App\Modules\Finance\Services;

use App\Models\BudgetLoan;
use App\Models\Competition;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Modules\Squad\Services\SquadService;
use Carbon\Carbon;

class BudgetProjectionService
{
    public function __construct(
        private readonly SquadService $squadService,
    ) {}
    /**
     * UEFA and RFEF solidarity funds (€250K)
     */
    private const SOLIDARITY_FUNDS = 100_000_000; // €1M in cents

    /**
     * Minimum transfer budget guaranteed after mandatory infrastructure.
     */
    private const MINIMUM_TRANSFER_BUDGET = 100_000_000; // €1M in cents

    /**
     * Maximum stadium seats used for commercial revenue calculation.
     * Prevents oversized stadiums from generating disproportionate commercial income.
     */
    private const MAX_COMMERCIAL_SEATS = 80_000;

    /**
     * Generate season projections for a game.
     * Called at the start of each season during pre-season.
     */
    public function generateProjections(Game $game): GameFinances
    {
        // Get user's team and league
        $team = $game->team;
        $league = $game->competition;

        // Calculate squad strengths for all teams in the league
        $teamStrengths = $this->squadService->calculateLeagueStrengths($game, $league);

        // Get user's projected position
        $projectedPosition = $this->squadService->getProjectedPosition($team->id, $teamStrengths);

        // Calculate projected revenues
        $projectedTvRevenue = $this->calculateTvRevenue($projectedPosition, $league);
        $projectedMatchdayRevenue = $this->calculateMatchdayRevenue($team, $game);
        $projectedSolidarityFundsRevenue = ($game->competition->tier > 1) ? self::SOLIDARITY_FUNDS : 0;
        $projectedCommercialRevenue = $this->getBaseCommercialRevenue($game, $team, $league);

        $projectedTotalRevenue = $projectedTvRevenue
            + $projectedMatchdayRevenue
            + $projectedSolidarityFundsRevenue
            + $projectedCommercialRevenue;

        // Calculate projected wages
        $projectedWages = $this->calculateProjectedWages($game);

        // Calculate operating expenses based on club reputation
        $reputation = TeamReputation::resolveLevel($game->id, $team->id);
        $baseOperatingExpenses = config('finances.operating_expenses.' . $reputation, 700_000_000);
        $tierMultiplier = config('finances.operating_expense_tier_multiplier.' . $league->tier, 1.0);
        $projectedOperatingExpenses = (int) ($baseOperatingExpenses * $tierMultiplier);

        // Calculate projected surplus
        $projectedSurplus = $projectedTotalRevenue - $projectedWages - $projectedOperatingExpenses;

        // Get carried debt, surplus, and loan repayment from previous season
        $carriedDebt = $this->getCarriedDebt($game);
        $carriedSurplus = $this->getCarriedSurplus($game);
        $previousLoanRepayment = $this->getPreviousSeasonLoanRepayment($game);

        // Calculate public subsidy if needed to guarantee minimum viable budget
        $projectedSubsidyRevenue = $this->calculateSubsidy(
            $projectedSurplus, $carriedDebt, $carriedSurplus, $previousLoanRepayment
        );
        if ($projectedSubsidyRevenue > 0) {
            $projectedTotalRevenue += $projectedSubsidyRevenue;
            $projectedSurplus += $projectedSubsidyRevenue;
        }

        // Create or update finances record
        $finances = GameFinances::updateOrCreate(
            [
                'game_id' => $game->id,
                'season' => $game->season,
            ],
            [
                'projected_position' => $projectedPosition,
                'projected_tv_revenue' => $projectedTvRevenue,
                'projected_solidarity_funds_revenue' => $projectedSolidarityFundsRevenue,
                'projected_matchday_revenue' => $projectedMatchdayRevenue,
                'projected_commercial_revenue' => $projectedCommercialRevenue,
                'projected_subsidy_revenue' => $projectedSubsidyRevenue,
                'projected_total_revenue' => $projectedTotalRevenue,
                'projected_wages' => $projectedWages,
                'projected_operating_expenses' => $projectedOperatingExpenses,
                'projected_surplus' => $projectedSurplus,
                'carried_debt' => $carriedDebt,
                'carried_surplus' => $carriedSurplus,
                'previous_loan_repayment' => $previousLoanRepayment,
            ]
        );

        return $finances;
    }

    /**
     * Calculate TV revenue based on position and league.
     */
    public function calculateTvRevenue(int $position, Competition $league): int
    {
        $config = $league->getConfig();

        return $config->getTvRevenue($position);
    }

    /**
     * Calculate matchday revenue.
     * Formula: Base (stadium_seats × revenue_per_seat) × Facilities Multiplier
     */
    public function calculateMatchdayRevenue(Team $team, Game $game): int
    {
        $reputation = TeamReputation::resolveLevel($game->id, $team->id);

        // Base matchday revenue from stadium size and competition config rates
        $base = $team->stadium_seats * config("finances.revenue_per_seat.{$reputation}", 15_000);

        // Get facilities multiplier from current investment (default to Tier 1 = 1.0)
        $investment = $game->currentInvestment;
        $facilitiesMultiplier = $investment
            ? GameInvestment::FACILITIES_MULTIPLIER[$investment->facilities_tier] ?? 1.0
            : 1.0;

        return (int) ($base * $facilitiesMultiplier);
    }

    /**
     * Calculate projected wages for the season.
     */
    public function calculateProjectedWages(Game $game): int
    {
        return GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereDoesntHave('activeLoan', function ($q) use ($game) {
                $q->where('loan_team_id', $game->team_id);
            })
            ->sum('annual_wage');
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
     * Get carried debt from previous season.
     */
    public function getCarriedDebt(Game $game): int
    {
        $netPosition = $this->getPreviousSeasonNetPosition($game);

        return $netPosition < 0 ? abs($netPosition) : 0;
    }

    /**
     * Get carried surplus from previous season.
     */
    public function getCarriedSurplus(Game $game): int
    {
        $netPosition = $this->getPreviousSeasonNetPosition($game);

        return $netPosition > 0 ? $netPosition : 0;
    }

    /**
     * Calculate the net cash position at the end of the previous season.
     *
     * Net = actual_surplus + carried_surplus - carried_debt - infrastructure - transfer_purchases
     *
     * This accounts for ALL money flows: revenue performance (variance),
     * unspent transfer budget, and prior carry-overs.
     */
    private function getPreviousSeasonNetPosition(Game $game): int
    {
        $previousSeason = (int) $game->season - 1;

        $previousFinances = GameFinances::where('game_id', $game->id)
            ->where('season', $previousSeason)
            ->first();

        if (!$previousFinances || $previousFinances->actual_surplus === 0 && $previousFinances->actual_total_revenue === 0) {
            return 0;
        }

        // Infrastructure committed during the previous season
        $previousInvestment = GameInvestment::where('game_id', $game->id)
            ->where('season', $previousSeason)
            ->first();

        $infrastructure = $previousInvestment?->total_infrastructure ?? 0;

        // Actual transfer spending (player purchases) during the previous season
        $seasonStart = Carbon::createFromDate($previousSeason, 7, 1);
        $seasonEnd = Carbon::createFromDate($previousSeason + 1, 6, 30);

        $transferSpending = FinancialTransaction::where('game_id', $game->id)
            ->whereBetween('transaction_date', [$seasonStart, $seasonEnd])
            ->where('category', FinancialTransaction::CATEGORY_TRANSFER_OUT)
            ->where('type', FinancialTransaction::TYPE_EXPENSE)
            ->sum('amount');

        return $previousFinances->actual_surplus
            + $previousFinances->carried_surplus
            - $previousFinances->carried_debt
            - $infrastructure
            - $transferSpending;
    }

    /**
     * Get the previous season's budget loan repayment amount.
     * Shown as a separate deduction in the budget flow.
     */
    public function getPreviousSeasonLoanRepayment(Game $game): int
    {
        $previousSeason = (int) $game->season - 1;

        return (int) BudgetLoan::where('game_id', $game->id)
            ->where('season', $previousSeason)
            ->where('status', BudgetLoan::STATUS_REPAID)
            ->sum('repayment_amount');
    }

    /**
     * Get base commercial revenue for budget projections.
     * Season 2+: uses previous season's actual commercial revenue.
     * Season 1: calculates from stadium_seats × config rate.
     */
    private function getBaseCommercialRevenue(Game $game, Team $team, Competition $league): int|float
    {
        // Check for prior season actual commercial revenue
        $previousSeason = (int) $game->season - 1;
        $previousFinances = GameFinances::where('game_id', $game->id)
            ->where('season', $previousSeason)
            ->first();

        if ($previousFinances && $previousFinances->actual_commercial_revenue > 0) {
            return $previousFinances->actual_commercial_revenue;
        }

        // First season: calculate from stadium seats × config rate (capped)
        $reputation = TeamReputation::resolveLevel($game->id, $team->id);
        $seats = min($team->stadium_seats, self::MAX_COMMERCIAL_SEATS);

        $base = $seats * config("finances.commercial_per_seat.{$reputation}", 80_000);

        // Reduce commercial revenue for lower tiers
        if ($league->tier > 1) {
            return $base * 0.75;
        }

        return $base;
    }

    /**
     * Calculate public subsidy (Subvenciones Públicas) to guarantee a minimum viable budget.
     * Ensures every team can cover mandatory infrastructure + a minimum transfer budget.
     */
    private function calculateSubsidy(int $projectedSurplus, int $carriedDebt, int $carriedSurplus, int $loanRepayment = 0): int
    {
        $minimumAvailable = GameInvestment::MINIMUM_TOTAL_INVESTMENT + self::MINIMUM_TRANSFER_BUDGET;
        $rawAvailable = $projectedSurplus + $carriedSurplus - $carriedDebt - $loanRepayment;

        if ($rawAvailable >= $minimumAvailable) {
            return 0;
        }

        return $minimumAvailable - $rawAvailable;
    }
}