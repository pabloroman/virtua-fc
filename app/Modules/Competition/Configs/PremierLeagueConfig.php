<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Models\ClubProfile;
use App\Models\Game;

class PremierLeagueConfig implements CompetitionConfig
{
    /**
     * Premier League TV revenue by position (in cents).
     * PL distributes more evenly than La Liga, with a higher floor.
     */
    private const TV_REVENUE = [
        1 => 18_000_000_000,   // €180M
        2 => 17_000_000_000,   // €170M
        3 => 16_000_000_000,   // €160M
        4 => 15_000_000_000,   // €150M
        5 => 14_200_000_000,   // €142M
        6 => 13_500_000_000,   // €135M
        7 => 13_000_000_000,   // €130M
        8 => 12_500_000_000,   // €125M
        9 => 12_000_000_000,   // €120M
        10 => 11_500_000_000,  // €115M
        11 => 11_000_000_000,  // €110M
        12 => 10_500_000_000,  // €105M
        13 => 10_000_000_000,  // €100M
        14 => 9_500_000_000,   // €95M
        15 => 9_000_000_000,   // €90M
        16 => 8_500_000_000,   // €85M
        17 => 8_000_000_000,   // €80M
        18 => 7_500_000_000,   // €75M
        19 => 7_000_000_000,   // €70M
        20 => 6_500_000_000,   // €65M
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

    public function getTopScorerAwardName(): string
    {
        return 'season.golden_boot';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.golden_glove';
    }

    public function getStandingsZones(): array
    {
        $slots = config('countries.EN.continental_slots.ENG1', []);

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
