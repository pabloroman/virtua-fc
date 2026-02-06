<?php

namespace App\Game\Competitions;

use App\Game\Contracts\CompetitionConfig;

/**
 * Default configuration for leagues without specific config.
 * Scales TV revenue based on position and number of teams.
 */
class DefaultLeagueConfig implements CompetitionConfig
{
    private int $numTeams;
    private int $baseTvRevenue;

    public function __construct(int $numTeams = 20, int $baseTvRevenue = 5_000_000_000)
    {
        $this->numTeams = $numTeams;
        $this->baseTvRevenue = $baseTvRevenue; // €50M default base
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
}
