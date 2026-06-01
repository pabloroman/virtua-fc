<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;

class ConferenceLeagueConfig implements CompetitionConfig
{
    /**
     * UECL knockout round prize money (in cents).
     */
    private const KNOCKOUT_PRIZE_MONEY = [
        1 => 275_000_000,      // €2.75M — win Knockout Playoff = reach R16
        2 => 310_000_000,      // €3.1M  — win R16 = reach QF
        3 => 375_000_000,      // €3.75M — win QF = reach SF
        4 => 460_000_000,      // €4.6M  — win SF = reach Final
        5 => 625_000_000,      // €6.25M — win Final (champion)
    ];

    public function getTvRevenue(int $position): int
    {
        // Conference League prize money is roughly 25% of UCL.
        // Bundles the flat participation fee + league-phase performance + final-ranking bonus.
        $base = 500_000_000; // €5M floor
        $positionBonus = max(0, 37 - $position) * 10_000_000; // €0.1M per position

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
        if ($position <= 8) {
            return 325_000_000; // €3.25M = €0.5M top-8 finish + €2.75M reach-R16 (direct, skips the playoff)
        }
        if ($position <= 24) {
            return 25_000_000;  // €250K — these teams earn the €2.75M reach-R16 by winning the playoff (round 1)
        }

        return 0; // Eliminated
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
