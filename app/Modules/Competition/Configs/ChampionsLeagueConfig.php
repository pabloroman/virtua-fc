<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;

class ChampionsLeagueConfig implements CompetitionConfig
{
    /**
     * UCL knockout round prize money (in cents).
     */
    private const KNOCKOUT_PRIZE_MONEY = [
        1 => 1_100_000_000,    // €11M   — win Knockout Playoff = reach R16
        2 => 1_250_000_000,    // €12.5M — win R16 = reach QF
        3 => 1_500_000_000,    // €15M   — win QF = reach SF
        4 => 1_850_000_000,    // €18.5M — win SF = reach Final
        5 => 2_500_000_000,    // €25M   — win Final (champion)
    ];

    /**
     * UCL prize money by league phase position (in cents).
     * Bundles the flat participation fee + league-phase performance + final-ranking bonus.
     */
    private const TV_REVENUE = [
        1 => 3_400_000_000,    // €34M
        2 => 3_360_000_000,    // €33.6M
        3 => 3_320_000_000,    // €33.2M
        4 => 3_280_000_000,    // €32.8M
        5 => 3_240_000_000,    // €32.4M
        6 => 3_200_000_000,    // €32M
        7 => 3_160_000_000,    // €31.6M
        8 => 3_120_000_000,    // €31.2M (direct R16)
        9 => 3_080_000_000,    // €30.8M
        10 => 3_040_000_000,   // €30.4M
        11 => 3_000_000_000,   // €30M
        12 => 2_960_000_000,   // €29.6M
        13 => 2_920_000_000,   // €29.2M
        14 => 2_880_000_000,   // €28.8M
        15 => 2_840_000_000,   // €28.4M
        16 => 2_800_000_000,   // €28M
        17 => 2_760_000_000,   // €27.6M
        18 => 2_720_000_000,   // €27.2M
        19 => 2_680_000_000,   // €26.8M
        20 => 2_640_000_000,   // €26.4M
        21 => 2_600_000_000,   // €26M
        22 => 2_560_000_000,   // €25.6M
        23 => 2_520_000_000,   // €25.2M
        24 => 2_480_000_000,   // €24.8M (last playoff spot)
        25 => 2_440_000_000,   // €24.4M (eliminated)
        26 => 2_400_000_000,   // €24M
        27 => 2_360_000_000,   // €23.6M
        28 => 2_320_000_000,   // €23.2M
        29 => 2_280_000_000,   // €22.8M
        30 => 2_240_000_000,   // €22.4M
        31 => 2_200_000_000,   // €22M
        32 => 2_160_000_000,   // €21.6M
        33 => 2_120_000_000,   // €21.2M
        34 => 2_080_000_000,   // €20.8M
        35 => 2_040_000_000,   // €20.4M
        36 => 2_000_000_000,   // €20M
    ];

    public function getTvRevenue(int $position): int
    {
        return self::TV_REVENUE[$position] ?? self::TV_REVENUE[36];
    }

    public function getPositionFactor(int $position): float
    {
        if ($position <= 8) {
            return 1.15;
        }
        if ($position <= 24) {
            return 1.05;
        }

        return 0.95;
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
            return 1_300_000_000; // €13M = €2M top-8 finish + €11M reach-R16 (direct, skips the playoff)
        }
        if ($position <= 24) {
            return 100_000_000; // €1M — these teams earn the €11M reach-R16 by winning the playoff (round 1)
        }

        return 0; // Eliminated
    }

    public function getStandingsZones(): array
    {
        return [
            [
                'minPosition' => 1,
                'maxPosition' => 8,
                'borderColor' => 'blue-500',
                'bgColor' => 'bg-blue-500',
                'label' => 'game.ucl_direct_knockout',
            ],
            [
                'minPosition' => 9,
                'maxPosition' => 24,
                'borderColor' => 'yellow-500',
                'bgColor' => 'bg-yellow-500',
                'label' => 'game.ucl_knockout_playoff',
            ],
            [
                'minPosition' => 25,
                'maxPosition' => 36,
                'borderColor' => 'red-500',
                'bgColor' => 'bg-red-500',
                'label' => 'game.ucl_eliminated',
            ],
        ];
    }

}
