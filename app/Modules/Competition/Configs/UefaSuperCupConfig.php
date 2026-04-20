<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;

class UefaSuperCupConfig implements CompetitionConfig
{
    /**
     * UEFA Super Cup prize money (in cents). Single match: only the winner
     * of round 1 (the final) is rewarded.
     */
    private const KNOCKOUT_PRIZE_MONEY = [
        1 => 500_000_000, // €5M — Winner
    ];

    public function getTvRevenue(int $position): int
    {
        return 0;
    }

    public function getPositionFactor(int $position): float
    {
        return 1.0;
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
        return [];
    }
}
