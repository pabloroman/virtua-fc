<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Models\ClubProfile;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameInvestment;
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
class SeasonSettlementProcessor implements SeasonEndProcessor
{

    public function priority(): int
    {
        return 15; // After archive (5), before standings reset (40)
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
        $actualMatchdayRevenue = $this->calculateMatchdayRevenue($game, $actualPosition);
        $actualCommercialRevenue = $this->calculateCommercialRevenue(
            $finances->projected_commercial_revenue, $actualPosition
        );
        $actualTransferIncome = $this->calculateTransferIncome($game);
        $actualCupBonusRevenue = $this->calculateCupBonusRevenue($game);

        // Guaranteed income — same amount as projected
        $actualSubsidyRevenue = $finances->projected_subsidy_revenue;
        $actualSolidarityFundsRevenue = $finances->projected_solidarity_funds_revenue;

        $actualTotalRevenue = $actualTvRevenue
            + $actualMatchdayRevenue
            + $actualCommercialRevenue
            + $actualSubsidyRevenue
            + $actualSolidarityFundsRevenue
            + $actualCupBonusRevenue
            + $actualTransferIncome;

        // Calculate actual wages (pro-rated for all players)
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
            'actual_commercial_revenue' => $actualCommercialRevenue,
            'actual_subsidy_revenue' => $actualSubsidyRevenue,
            'actual_transfer_income' => $actualTransferIncome,
            'actual_total_revenue' => $actualTotalRevenue,
            'actual_wages' => $actualWages,
            'actual_operating_expenses' => $actualOperatingExpenses,
            'actual_surplus' => $actualSurplus,
            'variance' => $variance,
        ]);

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
        ]);

        return $data;
    }

    private function calculateTvRevenue(int $position, Game $game): int
    {
        $league = $game->competition;
        $config = $league->getConfig();

        return $config->getTvRevenue($position);
    }

    private function calculateMatchdayRevenue(Game $game, int $position): int
    {
        $team = $game->team;
        $reputation = $team->clubProfile->reputation_level ?? ClubProfile::REPUTATION_MODEST;

        $league = $game->competition;

        $base = $team->stadium_seats * config("finances.revenue_per_seat.{$reputation}", 15_000);

        // Get facilities multiplier
        $investment = $game->currentInvestment;
        $facilitiesMultiplier = $investment
            ? GameInvestment::FACILITIES_MULTIPLIER[$investment->facilities_tier] ?? 1.0
            : 1.0;

        // Position factor from competition config
        $config = $league->getConfig();
        $positionFactor = $config->getPositionFactor($position);

        return (int) ($base * $facilitiesMultiplier * $positionFactor);
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

    private function calculateActualWages(Game $game): int
    {
        // Get all players currently on the squad
        $players = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->get();

        // Season runs from July 1 to June 30 (12 months)
        $seasonYear = (int) $game->season;
        $seasonStart = Carbon::createFromDate($seasonYear, 7, 1);
        $seasonEnd = Carbon::createFromDate($seasonYear + 1, 6, 30);
        $totalMonths = 12;

        // Batch-load mid-season join dates from transfers and loans
        $playerIds = $players->pluck('id');

        $transferDates = TransferOffer::where('game_id', $game->id)
            ->whereIn('game_player_id', $playerIds)
            ->where('status', TransferOffer::STATUS_COMPLETED)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->whereBetween('resolved_at', [$seasonStart, $seasonEnd])
            ->pluck('resolved_at', 'game_player_id');

        $loanDates = Loan::where('game_id', $game->id)
            ->whereIn('game_player_id', $playerIds)
            ->where('loan_team_id', $game->team_id)
            ->whereBetween('started_at', [$seasonStart, $seasonEnd])
            ->pluck('started_at', 'game_player_id');

        $totalWages = 0;

        foreach ($players as $player) {
            $joinDate = $transferDates[$player->id] ?? $loanDates[$player->id] ?? null;

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

        return $totalWages;
    }
}
