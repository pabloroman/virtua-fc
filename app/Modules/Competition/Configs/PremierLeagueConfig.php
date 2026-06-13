<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Models\ClubProfile;
use App\Models\Game;

class PremierLeagueConfig implements CompetitionConfig, HasSeasonGoals
{
    /**
     * Premier League TV revenue by position (in cents).
     *
     * The real PL broadcast deal is both the largest in world football (~€3.0B
     * total) and famously FLAT: the bottom club earns ~60% of the champion's
     * payout (huge equal-share domestic + international pots, a comparatively
     * small merit slice). The pool below totals ~€2.95B with a €110M floor and
     * a €195M ceiling (110/195 ≈ 56%), matching that real-world shape — far
     * flatter than La Liga's steep, merit-weighted curve.
     */
    private const TV_REVENUE = [
        1 => 19_500_000_000,   // €195M
        2 => 18_700_000_000,   // €187M
        3 => 18_000_000_000,   // €180M
        4 => 17_400_000_000,   // €174M
        5 => 16_800_000_000,   // €168M
        6 => 16_300_000_000,   // €163M
        7 => 15_800_000_000,   // €158M
        8 => 15_400_000_000,   // €154M
        9 => 15_000_000_000,   // €150M
        10 => 14_600_000_000,  // €146M
        11 => 14_200_000_000,  // €142M
        12 => 13_900_000_000,  // €139M
        13 => 13_600_000_000,  // €136M
        14 => 13_300_000_000,  // €133M
        15 => 13_000_000_000,  // €130M
        16 => 12_700_000_000,  // €127M
        17 => 12_400_000_000,  // €124M
        18 => 12_000_000_000,  // €120M
        19 => 11_500_000_000,  // €115M
        20 => 11_000_000_000,  // €110M
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
        Game::GOAL_EUROPA_LEAGUE => ['targetPosition' => 6, 'label' => 'game.goal_europa_league'],
        Game::GOAL_TOP_HALF => ['targetPosition' => 10, 'label' => 'game.goal_top_half'],
        Game::GOAL_SURVIVAL => ['targetPosition' => 17, 'label' => 'game.goal_survival'],
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
