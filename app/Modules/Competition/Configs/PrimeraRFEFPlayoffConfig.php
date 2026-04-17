<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;

/**
 * Minimal config for ESP3PO (the Primera RFEF promotion playoff).
 *
 * ESP3PO is a small two-round knockout cup (semifinals + final) populated
 * dynamically at the end of the Primera RFEF regular season with 8 teams
 * drawn from both groups into two fixed brackets. The two bracket winners
 * are promoted to La Liga 2.
 */
class PrimeraRFEFPlayoffConfig implements CompetitionConfig
{
    /**
     * Knockout prize money by round (in cents).
     * Round 1: Bracket semifinal. Round 2: Bracket final (promotion).
     */
    private const KNOCKOUT_PRIZE_MONEY = [
        1 => 20_000_000,   // €200K — reaching the bracket final
        2 => 100_000_000,  // €1M — winning the bracket final (promotion)
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
