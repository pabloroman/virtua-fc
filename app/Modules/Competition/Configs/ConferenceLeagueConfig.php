<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Models\ClubProfile;
use App\Models\Game;

class ConferenceLeagueConfig implements CompetitionConfig
{
    /**
     * UECL knockout round prize money (in cents).
     */
    private const KNOCKOUT_PRIZE_MONEY = [
        1 => 30_000_000,       // €300K - Knockout Playoff
        2 => 60_000_000,       // €600K - Round of 16
        3 => 100_000_000,      // €1M - Quarter-finals
        4 => 150_000_000,      // €1.5M - Semi-finals
        5 => 300_000_000,      // €3M - Final (winner)
    ];

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

    public function getTopScorerAwardName(): string
    {
        return 'season.top_scorer';
    }

    public function getBestGoalkeeperAwardName(): string
    {
        return 'season.best_goalkeeper';
    }

    public function getKnockoutPrizeMoney(int $roundNumber): int
    {
        return self::KNOCKOUT_PRIZE_MONEY[$roundNumber] ?? 0;
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
