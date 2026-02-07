<?php

namespace App\Game\Competitions;

use App\Game\Contracts\CompetitionConfig;
use App\Models\ClubProfile;
use App\Models\Game;

class LaLigaConfig implements CompetitionConfig
{
    /**
     * La Liga TV revenue by position (in cents).
     */
    private const TV_REVENUE = [
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

    private const POSITION_FACTORS = [
        'top' => 1.10,        // 1st-4th
        'mid_high' => 1.0,    // 5th-10th
        'mid_low' => 0.95,    // 11th-16th
        'relegation' => 0.85, // 17th-20th
    ];

    /**
     * Season goals with target positions.
     */
    private const SEASON_GOALS = [
        Game::GOAL_TITLE => ['targetPosition' => 1, 'label' => 'game.goal_title'],
        Game::GOAL_CHAMPIONS_LEAGUE => ['targetPosition' => 4, 'label' => 'game.goal_champions_league'],
        Game::GOAL_EUROPA_LEAGUE => ['targetPosition' => 6, 'label' => 'game.goal_europa_league'],
        Game::GOAL_TOP_HALF => ['targetPosition' => 10, 'label' => 'game.goal_top_half'],
        Game::GOAL_SURVIVAL => ['targetPosition' => 17, 'label' => 'game.goal_survival'],
    ];

    /**
     * Map reputation to season goal.
     */
    private const REPUTATION_TO_GOAL = [
        ClubProfile::REPUTATION_ELITE => Game::GOAL_TITLE,
        ClubProfile::REPUTATION_CONTINENTAL => Game::GOAL_CHAMPIONS_LEAGUE,
        ClubProfile::REPUTATION_ESTABLISHED => Game::GOAL_EUROPA_LEAGUE,
        ClubProfile::REPUTATION_MODEST => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_LOCAL => Game::GOAL_SURVIVAL,
    ];

    public function getTvRevenue(int $position): int
    {
        return self::TV_REVENUE[$position] ?? self::TV_REVENUE[20];
    }

    public function getPositionFactor(int $position): float
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

    public function getMaxPositions(): int
    {
        return 20;
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

    public function getStandingsZones(): array
    {
        return [
            [
                'minPosition' => 1,
                'maxPosition' => 4,
                'borderColor' => 'blue-500',
                'bgColor' => 'bg-blue-500',
                'label' => 'game.champions_league',
            ],
            [
                'minPosition' => 5,
                'maxPosition' => 6,
                'borderColor' => 'orange-500',
                'bgColor' => 'bg-orange-500',
                'label' => 'game.europa_league',
            ],
            [
                'minPosition' => 18,
                'maxPosition' => 20,
                'borderColor' => 'red-500',
                'bgColor' => 'bg-red-500',
                'label' => 'game.relegation',
            ],
        ];
    }
}
