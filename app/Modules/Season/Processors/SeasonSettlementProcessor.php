<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Finance\Services\BudgetLoanService;
use App\Modules\Stadium\Services\MatchAttendanceService;
use App\Modules\Stadium\Services\NamingRightsService;
use App\Modules\Stadium\Services\SeasonTicketPricingService;
use App\Models\BudgetLoan;
use App\Models\FinancialTransaction;
use App\Models\TeamReputation;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\Loan;
use App\Models\TransferOffer;
use Carbon\Carbon;

/**
 * Calculates actual season revenue and settles the finances.
 * Computes variance between projected and actual, carrying debt if needed.
 * Runs after archive but before standings reset so we can use final position.
 */
class SeasonSettlementProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly MatchAttendanceService $matchAttendanceService,
        private readonly SeasonTicketPricingService $seasonTicketPricingService,
        private readonly NamingRightsService $namingRightsService,
    ) {}

    public function priority(): int
    {
        return 60;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $finances = $game->currentFinances;

        // If no finances record exists, skip settlement
        if (!$finances) {
            return $data;
        }

        // Get actual final position
        $standing = GameStanding::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();

        $actualPosition = $standing->position ?? $finances->projected_position;

        // Calculate actual revenues
        $actualTvRevenue = $this->calculateTvRevenue($actualPosition, $game);
        $actualMatchdayRevenue = $this->calculateMatchdayRevenue($game);
        $actualCommercialRevenue = $this->calculateCommercialRevenue(
            $finances->projected_commercial_revenue, $actualPosition
        );
        $actualTransferIncome = $this->calculateTransferIncome($game);
        $actualCupBonusRevenue = $this->calculateCupBonusRevenue($game);

        // Naming-rights income settles proportional to the realised gate, so
        // a season of empty seats earns the sponsor's lower end.
        $actualNamingRightsRevenue = $this->namingRightsService->settledRevenueForGame($game);

        // Guaranteed income — same amount as projected. Season ticket
        // revenue is collected up front at the season's start so it stays
        // locked to the projected figure (no variance).
        $actualSubsidyRevenue = $finances->projected_subsidy_revenue;
        $actualSolidarityFundsRevenue = $finances->projected_solidarity_funds_revenue;
        $actualSeasonTicketRevenue = $finances->projected_season_ticket_revenue;

        $actualTotalRevenue = $actualTvRevenue
            + $actualMatchdayRevenue
            + $actualSeasonTicketRevenue
            + $actualCommercialRevenue
            + $actualNamingRightsRevenue
            + $actualSubsidyRevenue
            + $actualSolidarityFundsRevenue
            + $actualCupBonusRevenue
            + $actualTransferIncome;

        // Calculate actual wages (pro-rated for owned players + loan salary transactions)
        $actualWages = $this->calculateActualWages($game);

        // Operating expenses are fixed costs — same as projected
        $actualOperatingExpenses = $finances->projected_operating_expenses;

        // Calculate actual surplus
        $actualSurplus = $actualTotalRevenue - $actualWages - $actualOperatingExpenses;

        // Calculate variance (difference between actual and projected surplus)
        $variance = $actualSurplus - $finances->projected_surplus;

        // Update finances with actuals
        $finances->update([
            'actual_tv_revenue' => $actualTvRevenue,
            'actual_solidarity_funds_revenue' => $actualSolidarityFundsRevenue,
            'actual_cup_bonus_revenue' => $actualCupBonusRevenue,
            'actual_matchday_revenue' => $actualMatchdayRevenue,
            'actual_season_ticket_revenue' => $actualSeasonTicketRevenue,
            'actual_commercial_revenue' => $actualCommercialRevenue,
            'actual_naming_rights_revenue' => $actualNamingRightsRevenue,
            'actual_subsidy_revenue' => $actualSubsidyRevenue,
            'actual_transfer_income' => $actualTransferIncome,
            'net_transfer_result' => $this->calculateNetTransferResult($game),
            'actual_total_revenue' => $actualTotalRevenue,
            'actual_wages' => $actualWages,
            'actual_operating_expenses' => $actualOperatingExpenses,
            'actual_surplus' => $actualSurplus,
            'variance' => $variance,
        ]);

        // Repay any active budget loan
        $loanRepayment = 0;
        $activeLoan = BudgetLoan::where('game_id', $game->id)
            ->where('status', BudgetLoan::STATUS_ACTIVE)
            ->first();

        if ($activeLoan) {
            $loanService = app(BudgetLoanService::class);
            $loanRepayment = $loanService->repayLoan($activeLoan);
        }

        // Store in metadata for season-end display
        $data->setMetadata('finances', [
            'projected_position' => $finances->projected_position,
            'actual_position' => $actualPosition,
            'projected_total_revenue' => $finances->projected_total_revenue,
            'actual_total_revenue' => $actualTotalRevenue,
            'projected_surplus' => $finances->projected_surplus,
            'actual_surplus' => $actualSurplus,
            'variance' => $variance,
            'has_debt' => $variance < 0,
            'loan_repayment' => $loanRepayment,
        ]);

        return $data;
    }

    private function calculateTvRevenue(int $position, Game $game): int
    {
        $league = $game->competition;
        $config = $league->getConfig();

        return $config->getTvRevenue($position);
    }

    /**
     * Sum actual matchday revenue across every played home fixture of the
     * season, using the per-fixture MatchAttendance row. Rows that are missing
     * (saves that span the Phase 1a upgrade) are backfilled in place via the
     * idempotent MatchAttendanceService, so the formula sees uniform coverage.
     *
     * Attending season-ticket holders are subtracted from each match's
     * attendance before the per-seat rate applies — they already paid up front
     * via the season ticket sale, so counting them again would inflate revenue.
     * Holders attend at (1 − noshow), matching how MatchAttendanceService
     * composes the gate (attending holders + walk-ups), so subtracting the
     * attending count recovers exactly the walk-up buyers and keeps this in
     * lockstep with the budget projection. The remainder represents walk-up /
     * single-ticket buyers and concessions, which is what `revenue_per_seat` is
     * calibrated to capture.
     *
     * `revenue_per_seat` is a per-seat per-SEASON rate. To spread it across
     * the fixture list we divide by the count of league home games — cup and
     * European home ties then add bonus revenue on top at the same seat rate,
     * which lines up with the demand curve already weighting those fixtures.
     */
    private function calculateMatchdayRevenue(Game $game): int
    {
        $team = $game->team;
        $reputation = TeamReputation::resolveLevel($game->id, $team->id);
        $league = $game->competition;

        $leagueHomeMatchCount = GameMatch::where('game_id', $game->id)
            ->where('competition_id', $league->id)
            ->where('home_team_id', $team->id)
            ->where('played', true)
            ->count();

        if ($leagueHomeMatchCount === 0) {
            return 0;
        }

        $perSeatSeasonRate = (int) config("stadium.revenue_per_seat.{$reputation}", 15_000);
        $perSeatMatchRate = $perSeatSeasonRate / $leagueHomeMatchCount;

        $seasonTicketHolders = $this->seasonTicketPricingService->soldSeasonTicketsForGame($game);

        // Only attending holders occupy seats that would otherwise be walk-up,
        // so subtract that count (not the full holder count) to isolate the
        // genuine gate. Mirrors MatchAttendanceService::composeSeasonTicketAttendance.
        $noShowRate = (float) config('stadium.season_ticket_noshow_rate', 0.05);
        $attendingHolders = (int) round($seasonTicketHolders * (1.0 - $noShowRate));

        $homeMatches = GameMatch::where('game_id', $game->id)
            ->where('home_team_id', $team->id)
            ->where('played', true)
            ->get();

        $total = 0.0;
        foreach ($homeMatches as $match) {
            $attendance = $this->matchAttendanceService->resolveForMatch($match, $game);
            if ($attendance === null) {
                // Neutral-venue finals don't feed the home club's matchday revenue.
                continue;
            }
            $walkup = max(0, $attendance->attendance - $attendingHolders);
            $total += $walkup * $perSeatMatchRate;
        }

        $investment = $game->currentInvestment;
        $facilitiesMultiplier = $investment
            ? GameInvestment::FACILITIES_MULTIPLIER[$investment->facilities_tier] ?? 1.0
            : 1.0;

        return (int) ($total * $facilitiesMultiplier);
    }

    /**
     * Apply position-based growth multiplier to projected commercial revenue.
     */
    private function calculateCommercialRevenue(int $projected, int $position): int
    {
        $thresholds = config('finances.commercial_growth', []);
        $multiplier = 1.0;

        foreach ($thresholds as $maxPosition => $factor) {
            if ($position <= $maxPosition) {
                $multiplier = $factor;
                break;
            }
        }

        return (int) ($projected * $multiplier);
    }

    private function calculateCupBonusRevenue(Game $game): int
    {
        return FinancialTransaction::where('game_id', $game->id)
            ->where('category', FinancialTransaction::CATEGORY_CUP_BONUS)
            ->where('type', FinancialTransaction::TYPE_INCOME)
            ->sum('amount');
    }

    private function calculateTransferIncome(Game $game): int
    {
        // Get transfer income from financial transactions (player sales)
        return FinancialTransaction::where('game_id', $game->id)
            ->where('category', FinancialTransaction::CATEGORY_TRANSFER_IN)
            ->where('type', FinancialTransaction::TYPE_INCOME)
            ->sum('amount');
    }

    /**
     * Net player-trading result for THIS season (sales − purchases), windowed
     * to the season's July 1 → June 30 span. Stored per season so the trailing
     * player-trading allowance ("plusvalías") that widens the salary cap reads
     * a clean, season-scoped history. May be negative for a net buyer.
     */
    private function calculateNetTransferResult(Game $game): int
    {
        $seasonYear = (int) $game->season;
        $seasonStart = Carbon::createFromDate($seasonYear, 7, 1);
        $seasonEnd = Carbon::createFromDate($seasonYear + 1, 6, 30);

        $sales = (int) FinancialTransaction::where('game_id', $game->id)
            ->whereBetween('transaction_date', [$seasonStart, $seasonEnd])
            ->where('category', FinancialTransaction::CATEGORY_TRANSFER_IN)
            ->where('type', FinancialTransaction::TYPE_INCOME)
            ->sum('amount');

        $purchases = (int) FinancialTransaction::where('game_id', $game->id)
            ->whereBetween('transaction_date', [$seasonStart, $seasonEnd])
            ->where('category', FinancialTransaction::CATEGORY_TRANSFER_OUT)
            ->where('type', FinancialTransaction::TYPE_EXPENSE)
            ->sum('amount');

        return $sales - $purchases;
    }

    private function calculateActualWages(Game $game): int
    {
        // Get all players currently on the squad, excluding loaned-in players
        // (their salary is tracked via CATEGORY_LOAN transactions instead)
        $loanedInPlayerIds = Loan::where('game_id', $game->id)
            ->where('loan_team_id', $game->team_id)
            ->pluck('game_player_id');

        $players = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereNotIn('id', $loanedInPlayerIds)
            ->get();

        // Season runs from July 1 to June 30 (12 months)
        $seasonYear = (int) $game->season;
        $seasonStart = Carbon::createFromDate($seasonYear, 7, 1);
        $seasonEnd = Carbon::createFromDate($seasonYear + 1, 6, 30);
        $totalMonths = 12;

        // Batch-load mid-season join dates from transfers
        $playerIds = $players->pluck('id');

        $transferDates = TransferOffer::where('game_id', $game->id)
            ->whereIn('game_player_id', $playerIds)
            ->where('status', TransferOffer::STATUS_COMPLETED)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->whereBetween('resolved_at', [$seasonStart, $seasonEnd])
            ->pluck('resolved_at', 'game_player_id');

        $totalWages = 0;

        foreach ($players as $player) {
            $joinDate = $transferDates[$player->id] ?? null;

            // No join date found = player was here all season (initial squad, youth academy, pre-contract)
            if (!$joinDate || Carbon::parse($joinDate)->lte($seasonStart)) {
                $totalWages += $player->annual_wage;
                continue;
            }

            // Joined during season, pro-rate
            $parsedJoinDate = Carbon::parse($joinDate);
            if ($parsedJoinDate->lt($seasonEnd)) {
                $monthsAtClub = $parsedJoinDate->diffInMonths($seasonEnd);
                $proRatedWage = (int) ($player->annual_wage * ($monthsAtClub / $totalMonths));
                $totalWages += $proRatedWage;
            }
        }

        // Add loan salary expenses (recorded as transactions when loans completed)
        $loanExpenses = FinancialTransaction::where('game_id', $game->id)
            ->where('category', FinancialTransaction::CATEGORY_LOAN)
            ->where('type', FinancialTransaction::TYPE_EXPENSE)
            ->sum('amount');

        return $totalWages + $loanExpenses;
    }
}
