<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Models\Game;

class KnockoutCupConfig implements CompetitionConfig
{
    /**
     * Copa del Rey knockout round prize money (in cents).
     */
    private const KNOCKOUT_PRIZE_MONEY = [
        1 => 10_000_000,       // €100K - Round of 64/32
        2 => 20_000_000,       // €200K - Round of 32/16
        3 => 30_000_000,       // €300K - Round of 16
        4 => 50_000_000,       // €500K - Quarter-finals
        5 => 100_000_000,      // €1M - Semi-finals
        6 => 200_000_000,      // €2M - Final
    ];

    public function getTvRevenue(int $position): int
    {
        return 0;
    }

    public function getPositionFactor(int $position): float
    {
        return 1.0;
    }

    public function getSeasonGoal(string $reputation): string
    {
        return Game::GOAL_TOP_HALF;
    }

    public function getGoalTargetPosition(string $goal): int
    {
        return 1;
    }

    public function getAvailableGoals(): array
    {
        return [];
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
        return self::KNOCKOUT_PRIZE_MONEY[$roundNumber] ?? self::KNOCKOUT_PRIZE_MONEY[1];
    }

    public function getStandingsZones(): array
    {
        return [];
    }
}
