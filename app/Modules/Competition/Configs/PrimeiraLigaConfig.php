<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Models\ClubProfile;
use App\Models\Game;

class PrimeiraLigaConfig implements CompetitionConfig, HasSeasonGoals
{
    /**
     * Liga Portugal TV revenue by position (in cents).
     *
     * The steepest curve of any modelled league: Portuguese clubs negotiate
     * broadcast rights individually, so the big three earn an order of
     * magnitude more than the rest of the division.
     */
    private const TV_REVENUE = [
        1 => 2_800_000_000,    // €28M
        2 => 2_400_000_000,    // €24M
        3 => 2_000_000_000,    // €20M
        4 => 1_200_000_000,    // €12M
        5 => 900_000_000,      // €9M
        6 => 750_000_000,      // €7.5M
        7 => 650_000_000,      // €6.5M
        8 => 550_000_000,      // €5.5M
        9 => 480_000_000,      // €4.8M
        10 => 430_000_000,     // €4.3M
        11 => 390_000_000,     // €3.9M
        12 => 350_000_000,     // €3.5M
        13 => 320_000_000,     // €3.2M
        14 => 290_000_000,     // €2.9M
        15 => 270_000_000,     // €2.7M
        16 => 250_000_000,     // €2.5M
        17 => 220_000_000,     // €2.2M
        18 => 200_000_000,     // €2M
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
        Game::GOAL_EUROPA_LEAGUE => ['targetPosition' => 4, 'label' => 'game.goal_europa_league'],
        Game::GOAL_TOP_HALF => ['targetPosition' => 9, 'label' => 'game.goal_top_half'],
        Game::GOAL_SURVIVAL => ['targetPosition' => 15, 'label' => 'game.goal_survival'],
    ];

    /**
     * Map reputation to season goal.
     */
    private const REPUTATION_TO_GOAL = [
        ClubProfile::REPUTATION_ELITE => Game::GOAL_TITLE,
        ClubProfile::REPUTATION_CONTINENTAL => Game::GOAL_EUROPA_LEAGUE,
        ClubProfile::REPUTATION_ESTABLISHED => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_MODEST => Game::GOAL_SURVIVAL,
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
        return 'season.top_scorer_primeira';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.best_goalkeeper_primeira';
    }

    public function getKnockoutPrizeMoney(int $roundsFromFinal): int
    {
        return 0;
    }

    public function getLeaguePhaseQualificationBonus(int $position): int
    {
        return 0;
    }

    public function getStandingsZones(): array
    {
        $slots = config('countries.PT.continental_slots.POR1', []);

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
