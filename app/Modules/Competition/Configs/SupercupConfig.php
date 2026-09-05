<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;

/**
 * Domestic supercup prize money (in cents), paid to the winner of each
 * round. A final four pays a semi-final win and then the trophy; a
 * two-club supercup only ever reaches round 1, which is its final.
 *
 * Sized against KnockoutCupConfig, where winning the Copa del Rey final
 * pays €2M: a supercup is a two-match August payday, worth more per tie
 * than an early cup round and less than a domestic cup run overall.
 */
class SupercupConfig implements CompetitionConfig
{
    private const KNOCKOUT_PRIZE_MONEY = [
        1 => 100_000_000, // €1M — semi-final (or the final of a two-club supercup)
        2 => 300_000_000, // €3M — winner
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

    public function getLeaguePhaseQualificationBonus(int $position): int
    {
        return 0;
    }

    public function getStandingsZones(): array
    {
        return [];
    }
}
