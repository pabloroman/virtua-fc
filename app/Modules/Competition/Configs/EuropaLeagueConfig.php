<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;

class EuropaLeagueConfig implements CompetitionConfig
{
    /**
     * UEL knockout round prize money (in cents).
     */
    private const KNOCKOUT_PRIZE_MONEY = [
        1 => 550_000_000,      // €5.5M  — win Knockout Playoff = reach R16
        2 => 625_000_000,      // €6.25M — win R16 = reach QF
        3 => 750_000_000,      // €7.5M  — win QF = reach SF
        4 => 925_000_000,      // €9.25M — win SF = reach Final
        5 => 1_250_000_000,    // €12.5M — win Final (champion)
    ];

    public function getTvRevenue(int $position): int
    {
        // Europa League prize money is roughly 50% of UCL.
        // Bundles the flat participation fee + league-phase performance + final-ranking bonus.
        $base = 1_000_000_000; // €10M floor
        $positionBonus = max(0, 37 - $position) * 20_000_000; // €0.2M per position

        return $base + $positionBonus;
    }

    public function getPositionFactor(int $position): float
    {
        if ($position <= 8) {
            return 1.10;
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
            return 650_000_000; // €6.5M = €1M top-8 finish + €5.5M reach-R16 (direct, skips the playoff)
        }
        if ($position <= 24) {
            return 50_000_000;  // €500K — these teams earn the €5.5M reach-R16 by winning the playoff (round 1)
        }

        return 0; // Eliminated
    }

    public function getStandingsZones(): array
    {
        return [
            [
                'minPosition' => 1,
                'maxPosition' => 8,
                'borderColor' => 'orange-500',
                'bgColor' => 'bg-orange-500',
                'label' => 'game.uel_direct_knockout',
            ],
            [
                'minPosition' => 9,
                'maxPosition' => 24,
                'borderColor' => 'yellow-500',
                'bgColor' => 'bg-yellow-500',
                'label' => 'game.uel_knockout_playoff',
            ],
            [
                'minPosition' => 25,
                'maxPosition' => 36,
                'borderColor' => 'red-500',
                'bgColor' => 'bg-red-500',
                'label' => 'game.uel_eliminated',
            ],
        ];
    }

}
