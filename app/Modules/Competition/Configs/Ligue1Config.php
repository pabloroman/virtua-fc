<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Models\ClubProfile;
use App\Models\Game;

class Ligue1Config implements CompetitionConfig
{
    /**
     * Ligue 1 TV revenue by position (in cents).
     * Lower overall than other top-5 leagues.
     */
    private const TV_REVENUE = [
        1 => 8_000_000_000,    // €80M
        2 => 7_200_000_000,    // €72M
        3 => 6_500_000_000,    // €65M
        4 => 6_000_000_000,    // €60M
        5 => 5_500_000_000,    // €55M
        6 => 5_000_000_000,    // €50M
        7 => 4_600_000_000,    // €46M
        8 => 4_200_000_000,    // €42M
        9 => 3_900_000_000,    // €39M
        10 => 3_600_000_000,   // €36M
        11 => 3_300_000_000,   // €33M
        12 => 3_100_000_000,   // €31M
        13 => 2_900_000_000,   // €29M
        14 => 2_700_000_000,   // €27M
        15 => 2_500_000_000,   // €25M
        16 => 2_300_000_000,   // €23M
        17 => 2_100_000_000,   // €21M
        18 => 2_000_000_000,   // €20M
    ];

    private const POSITION_FACTORS = [
        'top' => 1.10,        // 1st-3rd
        'mid_high' => 1.0,    // 4th-9th
        'mid_low' => 0.95,    // 10th-15th
        'relegation' => 0.85, // 16th-18th
    ];

    /**
     * Season goals with target positions.
     */
    private const SEASON_GOALS = [
        Game::GOAL_TITLE => ['targetPosition' => 1, 'label' => 'game.goal_title'],
        Game::GOAL_CHAMPIONS_LEAGUE => ['targetPosition' => 3, 'label' => 'game.goal_champions_league'],
        Game::GOAL_EUROPA_LEAGUE => ['targetPosition' => 4, 'label' => 'game.goal_europa_league'],
        Game::GOAL_TOP_HALF => ['targetPosition' => 9, 'label' => 'game.goal_top_half'],
        Game::GOAL_SURVIVAL => ['targetPosition' => 15, 'label' => 'game.goal_survival'],
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
        return self::TV_REVENUE[$position] ?? self::TV_REVENUE[18];
    }

    public function getPositionFactor(int $position): float
    {
        if ($position <= 3) {
            return self::POSITION_FACTORS['top'];
        }
        if ($position <= 9) {
            return self::POSITION_FACTORS['mid_high'];
        }
        if ($position <= 15) {
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
        return self::SEASON_GOALS[$goal]['targetPosition'] ?? 9;
    }

    public function getAvailableGoals(): array
    {
        return self::SEASON_GOALS;
    }

    public function getTopScorerAwardName(): string
    {
        return 'season.top_scorer_ligue1';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.best_goalkeeper_ligue1';
    }

    public function getKnockoutPrizeMoney(int $roundNumber): int
    {
        return 0;
    }

    public function getStandingsZones(): array
    {
        $slots = config('countries.FR.continental_slots.FRA1', []);

        $zones = [];

        if (!empty($slots['UCL'])) {
            $zones[] = [
                'minPosition' => min($slots['UCL']),
                'maxPosition' => max($slots['UCL']),
                'borderColor' => 'blue-500',
                'bgColor' => 'bg-blue-500',
                'label' => 'game.champions_league',
            ];
        }

        if (!empty($slots['UEL'])) {
            $zones[] = [
                'minPosition' => min($slots['UEL']),
                'maxPosition' => max($slots['UEL']),
                'borderColor' => 'orange-500',
                'bgColor' => 'bg-orange-500',
                'label' => 'game.europa_league',
            ];
        }

        if (!empty($slots['UECL'])) {
            $zones[] = [
                'minPosition' => min($slots['UECL']),
                'maxPosition' => max($slots['UECL']),
                'borderColor' => 'green-500',
                'bgColor' => 'bg-green-500',
                'label' => 'game.conference_league',
            ];
        }

        $zones[] = [
            'minPosition' => 16,
            'maxPosition' => 18,
            'borderColor' => 'red-500',
            'bgColor' => 'bg-red-500',
            'label' => 'game.relegation',
        ];

        return $zones;
    }
}
