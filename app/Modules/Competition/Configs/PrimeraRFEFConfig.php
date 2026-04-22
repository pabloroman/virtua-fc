<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Models\ClubProfile;
use App\Models\Game;

/**
 * Primera RFEF (Spanish tier 3) — shared config for ESP3A and ESP3B.
 *
 * Each group is a self-contained 20-team flat league. Position 1 in each group
 * earns direct promotion to La Liga 2; positions 2–5 qualify for the promotion
 * playoff (modeled as the separate ESP3PO competition).
 */
class PrimeraRFEFConfig implements CompetitionConfig, HasSeasonGoals
{
    /**
     * Primera RFEF TV revenue — flat €100K per club, regardless of finishing position.
     * The tier-3 centralised pool is small and not position-weighted in-game.
     */
    private const TV_REVENUE_FLAT = 15_000_000; // €150K in cents

    private const POSITION_FACTORS = [
        'top' => 1.05,      // 1st-5th (promotion zone)
        'mid_high' => 1.0,  // 6th-10th
        'mid_low' => 0.95,  // 11th-15th
        'bottom' => 0.85,   // 16th-20thnever
    ];

    /**
     * Season goals with target positions (positions are per-group, 1–20).
     */
    private const SEASON_GOALS = [
        Game::GOAL_PROMOTION => ['targetPosition' => 1, 'label' => 'game.goal_promotion'],
        Game::GOAL_PLAYOFF => ['targetPosition' => 5, 'label' => 'game.goal_playoff'],
        Game::GOAL_TOP_HALF => ['targetPosition' => 10, 'label' => 'game.goal_top_half'],
        Game::GOAL_SURVIVAL => ['targetPosition' => 17, 'label' => 'game.goal_survival'],
    ];

    /**
     * Map reputation to season goal.
     */
    private const REPUTATION_TO_GOAL = [
        ClubProfile::REPUTATION_ELITE => Game::GOAL_PROMOTION,
        ClubProfile::REPUTATION_CONTINENTAL => Game::GOAL_PROMOTION,
        ClubProfile::REPUTATION_ESTABLISHED => Game::GOAL_PLAYOFF,
        ClubProfile::REPUTATION_MODEST => Game::GOAL_PLAYOFF,
        ClubProfile::REPUTATION_LOCAL => Game::GOAL_TOP_HALF,
    ];

    public function getTvRevenue(int $position): int
    {
        return self::TV_REVENUE_FLAT;
    }

    public function getPositionFactor(int $position): float
    {
        if ($position <= 5) {
            return self::POSITION_FACTORS['top'];
        }
        if ($position <= 10) {
            return self::POSITION_FACTORS['mid_high'];
        }
        if ($position <= 15) {
            return self::POSITION_FACTORS['mid_low'];
        }
        return self::POSITION_FACTORS['bottom'];
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
        return 'season.pichichi';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.zamora';
    }

    public function getKnockoutPrizeMoney(int $roundNumber): int
    {
        return 0;
    }

    public function getStandingsZones(): array
    {
        return [
            [
                'minPosition' => 1,
                'maxPosition' => 1,
                'borderColor' => 'green-500',
                'bgColor' => 'bg-green-500',
                'label' => 'game.direct_promotion',
            ],
            [
                'minPosition' => 2,
                'maxPosition' => 5,
                'borderColor' => 'green-300',
                'bgColor' => 'bg-green-300',
                'label' => 'game.promotion_playoff',
            ],
        ];
    }
}
