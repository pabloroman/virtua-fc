<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;

class KnockoutCupConfig implements CompetitionConfig
{
    /**
     * Domestic knockout cup prize money (in cents), keyed by how many rounds
     * remain after the one just won: 0 is the final, 1 the semi-final, and so
     * on back through the early rounds.
     *
     * Counting from the final is what lets one table serve cups of different
     * lengths — the Copa del Rey's seven rounds and a shorter cup's five —
     * without the early rounds of one being paid as the latter stages of another.
     */
    private const KNOCKOUT_PRIZE_MONEY = [
        0 => 200_000_000,      // €2M   - Final
        1 => 100_000_000,      // €1M   - Semi-finals
        2 => 50_000_000,       // €500K - Quarter-finals
        3 => 30_000_000,       // €300K - Round of 16
        4 => 20_000_000,       // €200K - Round of 32
        5 => 10_000_000,       // €100K - earlier rounds
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
        // A cup may have more preliminary rounds than the table describes;
        // they all pay the earliest-round rate.
        return self::KNOCKOUT_PRIZE_MONEY[$roundsFromFinal]
            ?? self::KNOCKOUT_PRIZE_MONEY[array_key_last(self::KNOCKOUT_PRIZE_MONEY)];
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
