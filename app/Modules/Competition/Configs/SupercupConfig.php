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
    /**
     * Keyed by rounds remaining after the one won: 0 is the final. A final
     * four pays a semi-final win and then the trophy; a two-club supercup
     * plays only round one, which is its final, and is paid as one.
     */
    private const KNOCKOUT_PRIZE_MONEY = [
        0 => 300_000_000, // €3M — winner
        1 => 100_000_000, // €1M — semi-final
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

    public function getKnockoutPrizeMoney(int $roundsFromFinal): int
    {
        return self::KNOCKOUT_PRIZE_MONEY[$roundsFromFinal] ?? 0;
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
