<?php

namespace App\Game\Competitions;

use App\Game\Contracts\CompetitionConfig;
use App\Models\ClubProfile;
use App\Models\Game;

class ChampionsLeagueConfig implements CompetitionConfig
{
    /**
     * UCL prize money by league phase position (in cents).
     * Based on UEFA coefficient + performance payments.
     */
    private const TV_REVENUE = [
        1 => 8_000_000_000,    // €80M
        2 => 7_500_000_000,    // €75M
        3 => 7_000_000_000,    // €70M
        4 => 6_500_000_000,    // €65M
        5 => 6_000_000_000,    // €60M
        6 => 5_500_000_000,    // €55M
        7 => 5_000_000_000,    // €50M
        8 => 4_800_000_000,    // €48M (direct R16)
        9 => 4_500_000_000,    // €45M
        10 => 4_300_000_000,   // €43M
        11 => 4_100_000_000,   // €41M
        12 => 3_900_000_000,   // €39M
        13 => 3_700_000_000,   // €37M
        14 => 3_500_000_000,   // €35M
        15 => 3_300_000_000,   // €33M
        16 => 3_100_000_000,   // €31M
        17 => 2_900_000_000,   // €29M
        18 => 2_800_000_000,   // €28M
        19 => 2_700_000_000,   // €27M
        20 => 2_600_000_000,   // €26M
        21 => 2_500_000_000,   // €25M
        22 => 2_400_000_000,   // €24M
        23 => 2_300_000_000,   // €23M
        24 => 2_200_000_000,   // €22M (last playoff spot)
        25 => 2_000_000_000,   // €20M (eliminated)
        26 => 1_900_000_000,   // €19M
        27 => 1_800_000_000,   // €18M
        28 => 1_700_000_000,   // €17M
        29 => 1_600_000_000,   // €16M
        30 => 1_500_000_000,   // €15M
        31 => 1_400_000_000,   // €14M
        32 => 1_300_000_000,   // €13M
        33 => 1_200_000_000,   // €12M
        34 => 1_100_000_000,   // €11M
        35 => 1_000_000_000,   // €10M
        36 => 900_000_000,     // €9M
    ];

    private const SEASON_GOALS = [
        Game::GOAL_TITLE => ['targetPosition' => 1, 'label' => 'game.goal_ucl_title'],
        Game::GOAL_CHAMPIONS_LEAGUE => ['targetPosition' => 8, 'label' => 'game.goal_ucl_knockout'],
    ];

    private const REPUTATION_TO_GOAL = [
        ClubProfile::REPUTATION_ELITE => Game::GOAL_TITLE,
        ClubProfile::REPUTATION_CONTENDERS => Game::GOAL_CHAMPIONS_LEAGUE,
        ClubProfile::REPUTATION_CONTINENTAL => Game::GOAL_CHAMPIONS_LEAGUE,
        ClubProfile::REPUTATION_ESTABLISHED => Game::GOAL_CHAMPIONS_LEAGUE,
        ClubProfile::REPUTATION_MODEST => Game::GOAL_CHAMPIONS_LEAGUE,
    ];

    public function getTvRevenue(int $position): int
    {
        return self::TV_REVENUE[$position] ?? self::TV_REVENUE[36];
    }

    public function getPositionFactor(int $position): float
    {
        if ($position <= 8) {
            return 1.15;
        }
        if ($position <= 24) {
            return 1.05;
        }

        return 0.95;
    }

    public function getSeasonGoal(string $reputation): string
    {
        return self::REPUTATION_TO_GOAL[$reputation] ?? Game::GOAL_CHAMPIONS_LEAGUE;
    }

    public function getGoalTargetPosition(string $goal): int
    {
        return self::SEASON_GOALS[$goal]['targetPosition'] ?? 8;
    }

    public function getAvailableGoals(): array
    {
        return self::SEASON_GOALS;
    }

    public function getTopScorerAwardName(): string
    {
        return 'season.top_scorer';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.best_goalkeeper';
    }

    public function getStandingsZones(): array
    {
        return [
            [
                'minPosition' => 1,
                'maxPosition' => 8,
                'borderColor' => 'blue-500',
                'bgColor' => 'bg-blue-500',
                'label' => 'game.ucl_direct_knockout',
            ],
            [
                'minPosition' => 9,
                'maxPosition' => 24,
                'borderColor' => 'yellow-500',
                'bgColor' => 'bg-yellow-500',
                'label' => 'game.ucl_knockout_playoff',
            ],
            [
                'minPosition' => 25,
                'maxPosition' => 36,
                'borderColor' => 'red-500',
                'bgColor' => 'bg-red-500',
                'label' => 'game.ucl_eliminated',
            ],
        ];
    }

}
