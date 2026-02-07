<?php

namespace App\Game\Competitions;

use App\Game\Contracts\CompetitionConfig;
use App\Models\ClubProfile;
use App\Models\Game;

class LaLiga2Config implements CompetitionConfig
{
    /**
     * La Liga 2 TV revenue by position (in cents).
     */
    private const TV_REVENUE = [
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

    private const POSITION_FACTORS = [
        'top' => 1.05,        // 1st-6th (promotion zone)
        'mid_high' => 1.0,    // 7th-12th
        'mid_low' => 0.95,    // 13th-18th
        'relegation' => 0.85, // 19th-22nd
    ];

    /**
     * Season goals with target positions.
     */
    private const SEASON_GOALS = [
        Game::GOAL_PROMOTION => ['targetPosition' => 2, 'label' => 'game.goal_promotion'],
        Game::GOAL_PLAYOFF => ['targetPosition' => 6, 'label' => 'game.goal_playoff'],
        Game::GOAL_TOP_HALF => ['targetPosition' => 11, 'label' => 'game.goal_top_half'],
        Game::GOAL_SURVIVAL => ['targetPosition' => 18, 'label' => 'game.goal_survival'],
    ];

    /**
     * Map reputation to season goal.
     */
    private const REPUTATION_TO_GOAL = [
        ClubProfile::REPUTATION_ELITE => Game::GOAL_PROMOTION,
        ClubProfile::REPUTATION_CONTINENTAL => Game::GOAL_PROMOTION,
        ClubProfile::REPUTATION_ESTABLISHED => Game::GOAL_PLAYOFF,
        ClubProfile::REPUTATION_MODEST => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_LOCAL => Game::GOAL_SURVIVAL,
    ];

    public function getTvRevenue(int $position): int
    {
        return self::TV_REVENUE[$position] ?? self::TV_REVENUE[22];
    }

    public function getPositionFactor(int $position): float
    {
        if ($position <= 6) {
            return self::POSITION_FACTORS['top'];
        }
        if ($position <= 12) {
            return self::POSITION_FACTORS['mid_high'];
        }
        if ($position <= 18) {
            return self::POSITION_FACTORS['mid_low'];
        }
        return self::POSITION_FACTORS['relegation'];
    }

    public function getMaxPositions(): int
    {
        return 22;
    }

    public function getSeasonGoal(string $reputation): string
    {
        return self::REPUTATION_TO_GOAL[$reputation] ?? Game::GOAL_TOP_HALF;
    }

    public function getGoalTargetPosition(string $goal): int
    {
        return self::SEASON_GOALS[$goal]['targetPosition'] ?? 11;
    }

    public function getAvailableGoals(): array
    {
        return self::SEASON_GOALS;
    }
}
