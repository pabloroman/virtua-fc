<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\GameStanding;

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

        $actualPosition = $standing?->position ?? $finances->projected_position;

        // Calculate actual revenues
        $actualTvRevenue = $this->calculateTvRevenue($actualPosition, $game);
        $actualMatchdayRevenue = $this->calculateMatchdayRevenue($game, $actualPosition);
        $actualPrizeRevenue = $this->calculatePrizeRevenue($game);
        $actualCommercialRevenue = $this->calculateCommercialRevenue(
            $finances->projected_commercial_revenue, $actualPosition
        );
        $actualTransferIncome = $this->calculateTransferIncome($game);

        $actualTotalRevenue = $actualTvRevenue
            + $actualMatchdayRevenue
            + $actualPrizeRevenue
            + $actualCommercialRevenue
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
            'actual_prize_revenue' => $actualPrizeRevenue,
            'actual_matchday_revenue' => $actualMatchdayRevenue,
            'actual_commercial_revenue' => $actualCommercialRevenue,
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
        $league = $game->team->competitions()->where('type', 'league')->first();
        if (!$league) {
            return 0;
        }

        $config = $league->getConfig();
        return $config->getTvRevenue($position);
    }

    private function calculateMatchdayRevenue(Game $game, int $position): int
    {
        $team = $game->team;
        $reputation = $team->clubProfile?->reputation_level ?? ClubProfile::REPUTATION_MODEST;

        $league = $team->competitions()->where('type', 'league')->first();
        if (!$league) {
            return 0;
        }

        $base = $team->stadium_seats * $league->getConfig()->getRevenuePerSeat($reputation);

        // Get facilities multiplier
        $investment = $game->currentInvestment;
        $facilitiesMultiplier = $investment
            ? GameInvestment::FACILITIES_MULTIPLIER[$investment->facilities_tier] ?? 1.0
            : 1.0;

        // Position factor from competition config
        $config = $league->getConfig();
        $positionFactor = $config->getPositionFactor($position) ?? 1.0;

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

    private function calculatePrizeRevenue(Game $game): int
    {
        // Prize revenue is calculated via FinancialTransactions during the season
        // (recorded when cups are won). We sum it up here.
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
        $seasonStart = \Carbon\Carbon::createFromDate($seasonYear, 7, 1);
        $seasonEnd = \Carbon\Carbon::createFromDate($seasonYear + 1, 6, 30);
        $totalMonths = 12;

        $totalWages = 0;

        foreach ($players as $player) {
            // If no joined_on date, assume they were here all season
            if (!$player->joined_on) {
                $totalWages += $player->annual_wage;
                continue;
            }

            // Calculate months at club during this season
            $joinDate = $player->joined_on;

            // If joined before season start, they were here all season
            if ($joinDate->lte($seasonStart)) {
                $totalWages += $player->annual_wage;
                continue;
            }

            // If joined during season, pro-rate
            if ($joinDate->lt($seasonEnd)) {
                $monthsAtClub = $joinDate->diffInMonths($seasonEnd);
                $proRatedWage = (int) ($player->annual_wage * ($monthsAtClub / $totalMonths));
                $totalWages += $proRatedWage;
            }
            // If joined after season end, no wages for this season (shouldn't happen)
        }

        return $totalWages;
    }
}
