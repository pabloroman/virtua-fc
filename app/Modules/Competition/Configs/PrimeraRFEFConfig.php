<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Models\ClubProfile;
use App\Models\Game;

/**
 * Primera RFEF (Spanish tier 3) — shared config for ESP3A and ESP3B.
 *
 * Each group is a self-contained 20-team flat league. Position 1 in each group
 * earns direct promotion to La Liga 2; positions 2–5 qualify for the promotion
 * playoff (modeled as the separate ESP3PO competition).
 */
class PrimeraRFEFConfig implements CompetitionConfig, HasSeasonGoals
{
    /**
     * Primera RFEF TV revenue by position within a 20-team group (in cents).
     * Values are provisional and scaled well below La Liga 2.
     */
    private const TV_REVENUE = [
        1 => 150_000_000,  // €1.5M
        2 => 140_000_000,
        3 => 130_000_000,
        4 => 120_000_000,
        5 => 110_000_000,
        6 => 100_000_000,
        7 => 95_000_000,
        8 => 90_000_000,
        9 => 85_000_000,
        10 => 80_000_000,
        11 => 75_000_000,
        12 => 70_000_000,
        13 => 65_000_000,
        14 => 60_000_000,
        15 => 55_000_000,
        16 => 55_000_000,
        17 => 50_000_000,
        18 => 50_000_000,
        19 => 50_000_000,
        20 => 50_000_000,
    ];

    private const POSITION_FACTORS = [
        'top' => 1.05,      // 1st-5th (promotion zone)
        'mid_high' => 1.0,  // 6th-10th
        'mid_low' => 0.95,  // 11th-15th
        'bottom' => 0.85,   // 16th-20th
    ];

    /**
     * Season goals with target positions (positions are per-group, 1–20).
     */
    private const SEASON_GOALS = [
        Game::GOAL_PROMOTION => ['targetPosition' => 1, 'label' => 'game.goal_promotion'],
        Game::GOAL_PLAYOFF => ['targetPosition' => 5, 'label' => 'game.goal_playoff'],
        Game::GOAL_TOP_HALF => ['targetPosition' => 10, 'label' => 'game.goal_top_half'],
        Game::GOAL_SURVIVAL => ['targetPosition' => 17, 'label' => 'game.goal_survival'],
    ];

    /**
     * Map reputation to season goal.
     */
    private const REPUTATION_TO_GOAL = [
        ClubProfile::REPUTATION_ELITE => Game::GOAL_PROMOTION,
        ClubProfile::REPUTATION_CONTINENTAL => Game::GOAL_PROMOTION,
        ClubProfile::REPUTATION_ESTABLISHED => Game::GOAL_PLAYOFF,
        ClubProfile::REPUTATION_MODEST => Game::GOAL_PLAYOFF,
        ClubProfile::REPUTATION_LOCAL => Game::GOAL_TOP_HALF,
    ];

    public function getTvRevenue(int $position): int
    {
        return self::TV_REVENUE[$position] ?? self::TV_REVENUE[20];
    }

    public function getPositionFactor(int $position): float
    {
        if ($position <= 5) {
            return self::POSITION_FACTORS['top'];
        }
        if ($position <= 10) {
            return self::POSITION_FACTORS['mid_high'];
        }
        if ($position <= 15) {
            return self::POSITION_FACTORS['mid_low'];
        }
        return self::POSITION_FACTORS['bottom'];
    }

    public function getSeasonGoal(string $reputation): string
    {
        return self::REPUTATION_TO_GOAL[$reputation] ?? Game::GOAL_TOP_HALF;
    }

    public function getGoalTargetPosition(string $goal): int
    {
        return self::SEASON_GOALS[$goal]['targetPosition'] ?? 10;
    }

    public function getAvailableGoals(): array
    {
        return self::SEASON_GOALS;
    }

    public function getTopScorerAwardName(): string
    {
        return 'season.pichichi';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.zamora';
    }

    public function getKnockoutPrizeMoney(int $roundNumber): int
    {
        return 0;
    }

    public function getStandingsZones(): array
    {
        return [
            [
                'minPosition' => 1,
                'maxPosition' => 1,
                'borderColor' => 'green-500',
                'bgColor' => 'bg-green-500',
                'label' => 'game.direct_promotion',
            ],
            [
                'minPosition' => 2,
                'maxPosition' => 5,
                'borderColor' => 'green-300',
                'bgColor' => 'bg-green-300',
                'label' => 'game.promotion_playoff',
            ],
        ];
    }
}
