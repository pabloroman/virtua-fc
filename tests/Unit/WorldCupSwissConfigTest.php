<?php

namespace Tests\Unit;

use App\Modules\Competition\Configs\WorldCupSwissConfig;
use PHPUnit\Framework\TestCase;

class WorldCupSwissConfigTest extends TestCase
{
    private WorldCupSwissConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new WorldCupSwissConfig();
    }

    public function test_national_teams_earn_no_money(): void
    {
        foreach ([1, 8, 24, 48] as $position) {
            $this->assertSame(0, $this->config->getTvRevenue($position));
            $this->assertSame(0, $this->config->getLeaguePhaseQualificationBonus($position));
        }

        foreach (range(1, 5) as $round) {
            $this->assertSame(0, $this->config->getKnockoutPrizeMoney($round));
        }
    }

    public function test_standings_zones_cover_all_48_positions(): void
    {
        $zones = $this->config->getStandingsZones();

        // Every position 1-48 falls into exactly one zone.
        for ($position = 1; $position <= 48; $position++) {
            $matching = array_filter(
                $zones,
                fn ($zone) => $position >= $zone['minPosition'] && $position <= $zone['maxPosition']
            );

            $this->assertCount(1, $matching, "Position {$position} is not in exactly one zone");
        }

        // The three bands mirror the UCL shape scaled to 48 teams.
        $this->assertSame(1, $zones[0]['minPosition']);
        $this->assertSame(8, $zones[0]['maxPosition']);
        $this->assertSame(9, $zones[1]['minPosition']);
        $this->assertSame(24, $zones[1]['maxPosition']);
        $this->assertSame(25, $zones[2]['minPosition']);
        $this->assertSame(48, $zones[2]['maxPosition']);
    }
}
