<?php

namespace App\Game\Competitions;

use App\Game\Contracts\CompetitionConfig;
use App\Models\ClubProfile;
use App\Models\Game;

/**
 * Default configuration for leagues without specific config.
 * Scales TV revenue based on position and number of teams.
 */
class DefaultLeagueConfig implements CompetitionConfig
{
    private int $numTeams;
    private int $baseTvRevenue;

    /**
     * Default reputation to goal mapping.
     */
    private const REPUTATION_TO_GOAL = [
        ClubProfile::REPUTATION_ELITE => Game::GOAL_TITLE,
        ClubProfile::REPUTATION_CONTENDERS => Game::GOAL_CHAMPIONS_LEAGUE,
        ClubProfile::REPUTATION_CONTINENTAL => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_ESTABLISHED => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_MODEST => Game::GOAL_TOP_HALF,
        ClubProfile::REPUTATION_PROFESSIONAL => Game::GOAL_SURVIVAL,
        ClubProfile::REPUTATION_LOCAL => Game::GOAL_SURVIVAL,
    ];

    public function __construct(int $numTeams = 20, int $baseTvRevenue = 5_000_000_000)
    {
        $this->numTeams = $numTeams;
        $this->baseTvRevenue = $baseTvRevenue; // €50M default base
    }

    public function getMinimumAnnualWage(): int
    {
        return 10_000_000; // €100K default
    }

    public function getTvRevenue(int $position): int
    {
        // Linear scale: 1st place gets 2x base, last place gets 0.8x base
        $positionRatio = 1 - (($position - 1) / max(1, $this->numTeams - 1));
        $multiplier = 0.8 + ($positionRatio * 1.2); // Range: 0.8x to 2.0x

        return (int) ($this->baseTvRevenue * $multiplier);
    }

    public function getPositionFactor(int $position): float
    {
        $topQuarter = (int) ceil($this->numTeams * 0.25);
        $midPoint = (int) ceil($this->numTeams * 0.5);
        $bottomQuarter = (int) ceil($this->numTeams * 0.75);

        if ($position <= $topQuarter) {
            return 1.10;
        }
        if ($position <= $midPoint) {
            return 1.0;
        }
        if ($position <= $bottomQuarter) {
            return 0.95;
        }
        return 0.85;
    }

    public function getMaxPositions(): int
    {
        return $this->numTeams;
    }

    public function getSeasonGoal(string $reputation): string
    {
        return self::REPUTATION_TO_GOAL[$reputation] ?? Game::GOAL_TOP_HALF;
    }

    public function getGoalTargetPosition(string $goal): int
    {
        // Calculate target positions dynamically based on league size
        $topQuarter = (int) ceil($this->numTeams * 0.05);      // ~1st place
        $europeanZone = (int) ceil($this->numTeams * 0.20);    // ~Top 4
        $midTable = (int) ceil($this->numTeams * 0.50);        // ~Top half
        $survivalZone = (int) ceil($this->numTeams * 0.85);    // ~17th of 20

        return match ($goal) {
            Game::GOAL_TITLE => max(1, $topQuarter),
            Game::GOAL_CHAMPIONS_LEAGUE => $europeanZone,
            Game::GOAL_EUROPA_LEAGUE => (int) ceil($this->numTeams * 0.30),
            Game::GOAL_TOP_HALF => $midTable,
            Game::GOAL_SURVIVAL => $survivalZone,
            Game::GOAL_PROMOTION => 2,
            Game::GOAL_PLAYOFF => (int) ceil($this->numTeams * 0.30),
            default => $midTable,
        };
    }

    public function getAvailableGoals(): array
    {
        return [
            Game::GOAL_TITLE => ['targetPosition' => $this->getGoalTargetPosition(Game::GOAL_TITLE), 'label' => 'game.goal_title'],
            Game::GOAL_CHAMPIONS_LEAGUE => ['targetPosition' => $this->getGoalTargetPosition(Game::GOAL_CHAMPIONS_LEAGUE), 'label' => 'game.goal_champions_league'],
            Game::GOAL_TOP_HALF => ['targetPosition' => $this->getGoalTargetPosition(Game::GOAL_TOP_HALF), 'label' => 'game.goal_top_half'],
            Game::GOAL_SURVIVAL => ['targetPosition' => $this->getGoalTargetPosition(Game::GOAL_SURVIVAL), 'label' => 'game.goal_survival'],
        ];
    }

    public function getStandingsZones(): array
    {
        // Calculate zones dynamically based on league size
        $europeanZone = (int) ceil($this->numTeams * 0.20);     // Top ~20% for European spots
        $relegationStart = $this->numTeams - 2;                  // Bottom 3 for relegation

        return [
            [
                'minPosition' => 1,
                'maxPosition' => $europeanZone,
                'borderColor' => 'blue-500',
                'bgColor' => 'bg-blue-500',
                'label' => 'game.champions_league',
            ],
            [
                'minPosition' => $relegationStart,
                'maxPosition' => $this->numTeams,
                'borderColor' => 'red-500',
                'bgColor' => 'bg-red-500',
                'label' => 'game.relegation',
            ],
        ];
    }
}
