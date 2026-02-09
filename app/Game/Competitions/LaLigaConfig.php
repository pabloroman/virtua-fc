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
        1 => 15_500_000_000,   // €155M
        2 => 14_000_000_000,   // €140M
        3 => 10_500_000_000,   // €105M
        4 => 7_200_000_000,    // €72M
        5 => 6_800_000_000,    // €68M
        6 => 6_500_000_000,    // €65M
        7 => 6_200_000_000,    // €62M
        8 => 5_800_000_000,    // €58M
        9 => 5_500_000_000,    // €55M
        10 => 5_200_000_000,   // €52M
        11 => 4_800_000_000,   // €48M
        12 => 4_600_000_000,   // €46M
        13 => 4_500_000_000,   // €45M
        14 => 4_400_000_000,   // €44M
        15 => 4_300_000_000,   // €43M
        16 => 4_300_000_000,   // €43M
        17 => 4_200_000_000,   // €42M
        18 => 4_200_000_000,   // €42M
        19 => 4_100_000_000,   // €41M
        20 => 4_000_000_000,   // €40M
    ];

    /**
     * Commercial revenue per seat per season by reputation level (in cents).
     */
    private const COMMERCIAL_PER_SEAT = [
        ClubProfile::REPUTATION_ELITE => 350_000,        // €3,500/seat
        ClubProfile::REPUTATION_CONTENDERS => 140_000,    // €1,400/seat
        ClubProfile::REPUTATION_CONTINENTAL => 150_000,   // €1,500/seat
        ClubProfile::REPUTATION_ESTABLISHED => 100_000,   // €1,000/seat
        ClubProfile::REPUTATION_MODEST => 80_000,         // €800/seat
        ClubProfile::REPUTATION_PROFESSIONAL => 50_000,   // €500/seat
        ClubProfile::REPUTATION_LOCAL => 20_000,          // €200/seat
    ];

    /**
     * Matchday revenue per seat per season by reputation level (in cents).
     */
    private const REVENUE_PER_SEAT = [
        ClubProfile::REPUTATION_ELITE => 130_000,       // €1,300/seat
        ClubProfile::REPUTATION_CONTENDERS => 80_000,    // €800/seat
        ClubProfile::REPUTATION_CONTINENTAL => 50_000,   // €500/seat
        ClubProfile::REPUTATION_ESTABLISHED => 25_000,   // €250/seat
        ClubProfile::REPUTATION_MODEST => 15_000,        // €150/seat
        ClubProfile::REPUTATION_PROFESSIONAL => 8_000,   // €80/seat
        ClubProfile::REPUTATION_LOCAL => 4_000,          // €40/seat
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
        ClubProfile::REPUTATION_CONTENDERS => Game::GOAL_CHAMPIONS_LEAGUE,
        ClubProfile::REPUTATION_CONTINENTAL => Game::GOAL_EUROPA_LEAGUE,
        ClubProfile::REPUTATION_ESTABLISHED => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_MODEST => Game::GOAL_SURVIVAL,
        ClubProfile::REPUTATION_PROFESSIONAL => Game::GOAL_SURVIVAL,
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

    public function getCommercialPerSeat(string $reputation): int
    {
        return self::COMMERCIAL_PER_SEAT[$reputation] ?? self::COMMERCIAL_PER_SEAT[ClubProfile::REPUTATION_MODEST];
    }

    public function getRevenuePerSeat(string $reputation): int
    {
        return self::REVENUE_PER_SEAT[$reputation] ?? self::REVENUE_PER_SEAT[ClubProfile::REPUTATION_MODEST];
    }
}
