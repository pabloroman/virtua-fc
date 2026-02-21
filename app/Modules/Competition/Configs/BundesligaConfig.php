<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Models\ClubProfile;
use App\Models\Game;

class BundesligaConfig implements CompetitionConfig
{
    /**
     * Bundesliga TV revenue by position (in cents).
     * More evenly distributed than La Liga.
     */
    private const TV_REVENUE = [
        1 => 11_000_000_000,   // €110M
        2 => 10_000_000_000,   // €100M
        3 => 9_200_000_000,    // €92M
        4 => 8_500_000_000,    // €85M
        5 => 8_000_000_000,    // €80M
        6 => 7_500_000_000,    // €75M
        7 => 7_000_000_000,    // €70M
        8 => 6_500_000_000,    // €65M
        9 => 6_000_000_000,    // €60M
        10 => 5_500_000_000,   // €55M
        11 => 5_000_000_000,   // €50M
        12 => 4_600_000_000,   // €46M
        13 => 4_200_000_000,   // €42M
        14 => 3_900_000_000,   // €39M
        15 => 3_600_000_000,   // €36M
        16 => 3_400_000_000,   // €34M
        17 => 3_200_000_000,   // €32M
        18 => 3_000_000_000,   // €30M
    ];

    private const POSITION_FACTORS = [
        'top' => 1.10,        // 1st-4th
        'mid_high' => 1.0,    // 5th-9th
        'mid_low' => 0.95,    // 10th-15th
        'relegation' => 0.85, // 16th-18th
    ];

    /**
     * Season goals with target positions.
     */
    private const SEASON_GOALS = [
        Game::GOAL_TITLE => ['targetPosition' => 1, 'label' => 'game.goal_title'],
        Game::GOAL_CHAMPIONS_LEAGUE => ['targetPosition' => 4, 'label' => 'game.goal_champions_league'],
        Game::GOAL_EUROPA_LEAGUE => ['targetPosition' => 6, 'label' => 'game.goal_europa_league'],
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
        if ($position <= 4) {
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
        return 'season.torjaegerkanone';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.best_goalkeeper_bundesliga';
    }

    public function getStandingsZones(): array
    {
        $slots = config('countries.DE.continental_slots.DEU1', []);

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
