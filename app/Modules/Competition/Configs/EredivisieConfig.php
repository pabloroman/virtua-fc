<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Models\ClubProfile;
use App\Models\Game;

class EredivisieConfig implements CompetitionConfig, HasSeasonGoals
{
    /**
     * Eredivisie TV revenue by position (in cents).
     *
     * A collective deal shared far more evenly than Liga Portugal's individual
     * rights, so the curve is flat: the champions earn roughly five times the
     * bottom club rather than fourteen times.
     */
    private const TV_REVENUE = [
        1 => 1_400_000_000,    // €14M
        2 => 1_250_000_000,    // €12.5M
        3 => 1_120_000_000,    // €11.2M
        4 => 1_000_000_000,    // €10M
        5 => 900_000_000,      // €9M
        6 => 820_000_000,      // €8.2M
        7 => 750_000_000,      // €7.5M
        8 => 690_000_000,      // €6.9M
        9 => 640_000_000,      // €6.4M
        10 => 590_000_000,     // €5.9M
        11 => 550_000_000,     // €5.5M
        12 => 510_000_000,     // €5.1M
        13 => 470_000_000,     // €4.7M
        14 => 440_000_000,     // €4.4M
        15 => 400_000_000,     // €4M
        16 => 370_000_000,     // €3.7M
        17 => 340_000_000,     // €3.4M
        18 => 300_000_000,     // €3M
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
        return 'season.top_scorer_eredivisie';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.best_goalkeeper_eredivisie';
    }

    public function getKnockoutPrizeMoney(int $roundNumber): int
    {
        return 0;
    }

    public function getLeaguePhaseQualificationBonus(int $position): int
    {
        return 0;
    }

    public function getStandingsZones(): array
    {
        $slots = config('countries.NL.continental_slots.NED1', []);

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
