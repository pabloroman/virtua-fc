<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Game\Services\BudgetProjectionService;
use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameFinances;
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
    /**
     * La Liga TV revenue by position (in cents).
     */
    private const TV_REVENUE_LALIGA = [
        1 => 10_000_000_000,   // €100M
        2 => 9_000_000_000,    // €90M
        3 => 8_500_000_000,    // €85M
        4 => 8_000_000_000,    // €80M
        5 => 7_500_000_000,    // €75M
        6 => 7_000_000_000,    // €70M
        7 => 6_500_000_000,    // €65M
        8 => 6_000_000_000,    // €60M
        9 => 5_800_000_000,    // €58M
        10 => 5_600_000_000,   // €56M
        11 => 5_400_000_000,   // €54M
        12 => 5_200_000_000,   // €52M
        13 => 5_000_000_000,   // €50M
        14 => 5_000_000_000,   // €50M
        15 => 4_800_000_000,   // €48M
        16 => 4_600_000_000,   // €46M
        17 => 4_400_000_000,   // €44M
        18 => 4_200_000_000,   // €42M
        19 => 4_000_000_000,   // €40M
        20 => 4_000_000_000,   // €40M
    ];

    /**
     * La Liga 2 TV revenue by position (in cents).
     */
    private const TV_REVENUE_LALIGA2 = [
        1 => 2_000_000_000,    // €20M
        2 => 1_800_000_000,    // €18M
        3 => 1_500_000_000,    // €15M
        4 => 1_400_000_000,    // €14M
        5 => 1_300_000_000,    // €13M
        6 => 1_200_000_000,    // €12M
        7 => 1_100_000_000,    // €11M
        8 => 1_050_000_000,    // €10.5M
        9 => 1_000_000_000,    // €10M
        10 => 950_000_000,     // €9.5M
        11 => 900_000_000,     // €9M
        12 => 850_000_000,     // €8.5M
        13 => 800_000_000,     // €8M
        14 => 800_000_000,     // €8M
        15 => 750_000_000,     // €7.5M
        16 => 700_000_000,     // €7M
        17 => 650_000_000,     // €6.5M
        18 => 650_000_000,     // €6.5M
        19 => 600_000_000,     // €6M
        20 => 600_000_000,     // €6M
        21 => 600_000_000,     // €6M
        22 => 600_000_000,     // €6M
    ];

    /**
     * Position factors for matchday revenue.
     */
    private const POSITION_FACTORS = [
        'top' => 1.10,      // 1st-4th
        'mid_high' => 1.0,  // 5th-10th
        'mid_low' => 0.95,  // 11th-16th
        'relegation' => 0.85, // 17th-20th
    ];

    /**
     * Copa del Rey prize money by round.
     */
    private const CUP_PRIZES = [
        1 => 10_000_000,      // Round of 64: €100K
        2 => 25_000_000,      // Round of 32: €250K
        3 => 50_000_000,      // Round of 16: €500K
        4 => 100_000_000,     // Quarter-finals: €1M
        5 => 200_000_000,     // Semi-finals: €2M
        6 => 500_000_000,     // Runner-up: €5M
        7 => 1_000_000_000,   // Winner: €10M (additional)
    ];

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
        $actualCommercialRevenue = $finances->projected_commercial_revenue; // Same as projected
        $actualTransferIncome = $this->calculateTransferIncome($game);

        $actualTotalRevenue = $actualTvRevenue
            + $actualMatchdayRevenue
            + $actualPrizeRevenue
            + $actualCommercialRevenue
            + $actualTransferIncome;

        // Calculate actual wages (pro-rated for all players)
        $actualWages = $this->calculateActualWages($game);

        // Calculate actual surplus
        $actualSurplus = $actualTotalRevenue - $actualWages;

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
        $isLaLiga = $league && $league->tier === 1;

        $table = $isLaLiga ? self::TV_REVENUE_LALIGA : self::TV_REVENUE_LALIGA2;

        return $table[$position] ?? $table[array_key_last($table)];
    }

    private function calculateMatchdayRevenue(Game $game, int $position): int
    {
        $clubProfile = $game->team->clubProfile;
        if (!$clubProfile) {
            return 0;
        }

        $base = $clubProfile->calculateBaseMatchdayRevenue();

        // Get facilities multiplier
        $investment = $game->currentInvestment;
        $facilitiesMultiplier = $investment
            ? GameInvestment::FACILITIES_MULTIPLIER[$investment->facilities_tier] ?? 1.0
            : 1.0;

        // Position factor
        $positionFactor = $this->getPositionFactor($position);

        return (int) ($base * $facilitiesMultiplier * $positionFactor);
    }

    private function getPositionFactor(int $position): float
    {
        if ($position <= 4) {
            return self::POSITION_FACTORS['top'];
        }
        if ($position <= 10) {
            return self::POSITION_FACTORS['mid_high'];
        }
        if ($position <= 16) {
            return self::POSITION_FACTORS['mid_low'];
        }
        return self::POSITION_FACTORS['relegation'];
    }

    private function calculatePrizeRevenue(Game $game): int
    {
        // Sum up cup prizes based on rounds reached
        $cupRound = $game->cup_round ?? 0;
        $prize = 0;

        for ($round = 1; $round <= $cupRound; $round++) {
            $prize += self::CUP_PRIZES[$round] ?? 0;
        }

        // Add winner bonus if they won (assumed if eliminated = false and cup_round >= 6)
        if (!$game->cup_eliminated && $cupRound >= 6) {
            $prize += self::CUP_PRIZES[7] ?? 0;
        }

        return $prize;
    }

    private function calculateTransferIncome(Game $game): int
    {
        // Get transfer income from financial transactions
        return FinancialTransaction::where('game_id', $game->id)
            ->where('category', FinancialTransaction::CATEGORY_TRANSFER)
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
