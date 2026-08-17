<?php

namespace App\Modules\Competition\Configs;

use App\Modules\Competition\Contracts\CompetitionConfig;

/**
 * Config for the imaginative "World Cup — Swiss Format" tournament (WCSWISS):
 * 48 national teams in a single Swiss league phase, top 24 into a knockout
 * bracket identical in shape to the UEFA Champions League.
 *
 * National teams have no club economy, so every monetary method returns 0 —
 * this competition exists purely for the sporting structure. The standings
 * zones mirror the UCL bands scaled to a 48-team field (1-8 direct to the
 * Round of 16, 9-24 into the knockout playoff, 25-48 eliminated).
 */
class WorldCupSwissConfig implements CompetitionConfig
{
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
        return 0;
    }

    public function getLeaguePhaseQualificationBonus(int $position): int
    {
        return 0;
    }

    public function getStandingsZones(): array
    {
        return [
            [
                'minPosition' => 1,
                'maxPosition' => 8,
                'borderColor' => 'blue-500',
                'bgColor' => 'bg-blue-500',
                'label' => 'game.wcswiss_direct_knockout',
            ],
            [
                'minPosition' => 9,
                'maxPosition' => 24,
                'borderColor' => 'yellow-500',
                'bgColor' => 'bg-yellow-500',
                'label' => 'game.wcswiss_knockout_playoff',
            ],
            [
                'minPosition' => 25,
                'maxPosition' => 48,
                'borderColor' => 'red-500',
                'bgColor' => 'bg-red-500',
                'label' => 'game.wcswiss_eliminated',
            ],
        ];
    }
}
