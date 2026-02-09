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
        1 => 900_000_000,      // €9M
        2 => 850_000_000,      // €8.5M
        3 => 800_000_000,      // €8M
        4 => 750_000_000,      // €7.5M
        5 => 700_000_000,      // €7M
        6 => 700_000_000,      // €7M
        7 => 650_000_000,      // €6.5M
        8 => 650_000_000,      // €6.5M
        9 => 650_000_000,      // €6.5M
        10 => 600_000_000,     // €6M
        11 => 600_000_000,     // €6M
        12 => 600_000_000,     // €6M
        13 => 600_000_000,     // €6M
        14 => 600_000_000,     // €6M
        15 => 550_000_000,     // €5.5M
        16 => 550_000_000,     // €5.5M
        17 => 550_000_000,     // €5.5M
        18 => 550_000_000,     // €5.5M
        19 => 500_000_000,     // €5M
        20 => 500_000_000,     // €5M
        21 => 500_000_000,     // €5M
        22 => 500_000_000,     // €5M
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
        ClubProfile::REPUTATION_CONTENDERS => Game::GOAL_PROMOTION,
        ClubProfile::REPUTATION_CONTINENTAL => Game::GOAL_PROMOTION,
        ClubProfile::REPUTATION_ESTABLISHED => Game::GOAL_PLAYOFF,
        ClubProfile::REPUTATION_MODEST => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_PROFESSIONAL => Game::GOAL_SURVIVAL,
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

    public function getStandingsZones(): array
    {
        return [
            [
                'minPosition' => 1,
                'maxPosition' => 2,
                'borderColor' => 'green-500',
                'bgColor' => 'bg-green-500',
                'label' => 'game.direct_promotion',
            ],
            [
                'minPosition' => 3,
                'maxPosition' => 6,
                'borderColor' => 'green-300',
                'bgColor' => 'bg-green-300',
                'label' => 'game.promotion_playoff',
            ],
            [
                'minPosition' => 19,
                'maxPosition' => 22,
                'borderColor' => 'red-500',
                'bgColor' => 'bg-red-500',
                'label' => 'game.relegation',
            ],
        ];
    }

}
