<?php

namespace App\Game\Competitions;

use App\Game\Contracts\CompetitionConfig;
use App\Models\ClubProfile;
use App\Models\Game;

class ConferenceLeagueConfig implements CompetitionConfig
{
    public function getTvRevenue(int $position): int
    {
        // Conference League prize money is roughly 25-30% of UCL
        $base = 250_000_000; // €2.5M base
        $positionBonus = max(0, 37 - $position) * 50_000_000; // €500K per position

        return $base + $positionBonus;
    }

    public function getPositionFactor(int $position): float
    {
        if ($position <= 8) {
            return 1.05;
        }
        if ($position <= 24) {
            return 1.0;
        }

        return 0.90;
    }

    public function getMaxPositions(): int
    {
        return 36;
    }

    public function getSeasonGoal(string $reputation): string
    {
        return Game::GOAL_EUROPA_LEAGUE;
    }

    public function getGoalTargetPosition(string $goal): int
    {
        return 8;
    }

    public function getAvailableGoals(): array
    {
        return [
            Game::GOAL_EUROPA_LEAGUE => ['targetPosition' => 8, 'label' => 'game.goal_uecl_knockout'],
        ];
    }

    public function getStandingsZones(): array
    {
        return [
            [
                'minPosition' => 1,
                'maxPosition' => 8,
                'borderColor' => 'green-500',
                'bgColor' => 'bg-green-500',
                'label' => 'game.uecl_direct_knockout',
            ],
            [
                'minPosition' => 9,
                'maxPosition' => 24,
                'borderColor' => 'yellow-500',
                'bgColor' => 'bg-yellow-500',
                'label' => 'game.uecl_knockout_playoff',
            ],
            [
                'minPosition' => 25,
                'maxPosition' => 36,
                'borderColor' => 'red-500',
                'bgColor' => 'bg-red-500',
                'label' => 'game.uecl_eliminated',
            ],
        ];
    }
}
