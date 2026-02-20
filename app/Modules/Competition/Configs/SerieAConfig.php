<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Models\ClubProfile;
use App\Models\Game;

class SerieAConfig implements CompetitionConfig
{
    /**
     * Serie A TV revenue by position (in cents).
     */
    private const TV_REVENUE = [
        1 => 12_000_000_000,   // €120M
        2 => 11_000_000_000,   // €110M
        3 => 10_000_000_000,   // €100M
        4 => 9_200_000_000,    // €92M
        5 => 8_500_000_000,    // €85M
        6 => 8_000_000_000,    // €80M
        7 => 7_500_000_000,    // €75M
        8 => 7_000_000_000,    // €70M
        9 => 6_500_000_000,    // €65M
        10 => 6_000_000_000,   // €60M
        11 => 5_500_000_000,   // €55M
        12 => 5_200_000_000,   // €52M
        13 => 4_800_000_000,   // €48M
        14 => 4_500_000_000,   // €45M
        15 => 4_200_000_000,   // €42M
        16 => 4_000_000_000,   // €40M
        17 => 3_800_000_000,   // €38M
        18 => 3_500_000_000,   // €35M
        19 => 3_200_000_000,   // €32M
        20 => 3_000_000_000,   // €30M
    ];

    private const POSITION_FACTORS = [
        'top' => 1.10,        // 1st-4th
        'mid_high' => 1.0,    // 5th-10th
        'mid_low' => 0.95,    // 11th-17th
        'relegation' => 0.85, // 18th-20th
    ];

    /**
     * Season goals with target positions.
     */
    private const SEASON_GOALS = [
        Game::GOAL_TITLE => ['targetPosition' => 1, 'label' => 'game.goal_title'],
        Game::GOAL_CHAMPIONS_LEAGUE => ['targetPosition' => 5, 'label' => 'game.goal_champions_league'],
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
        if ($position <= 17) {
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

    public function getTopScorerAwardName(): string
    {
        return 'season.capocannoniere';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.best_goalkeeper_serie_a';
    }

    public function getStandingsZones(): array
    {
        $slots = config('countries.IT.continental_slots.ITA1', []);

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
            'minPosition' => 18,
            'maxPosition' => 20,
            'borderColor' => 'red-500',
            'bgColor' => 'bg-red-500',
            'label' => 'game.relegation',
        ];

        return $zones;
    }
}
