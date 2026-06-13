<?php

namespace Tests\Unit\Competition;

use App\Modules\Competition\Configs\PremierLeagueConfig;
use Tests\TestCase;

/**
 * The Premier League TV pool is recalibrated to the real broadcast deal: the
 * largest in world football (~€3.0B) AND famously flat (the bottom club earns
 * ~60% of the champion's payout). These band/shape assertions lock that intent
 * so a future edit can't silently revert to a steep, low-floor curve.
 */
class PremierLeagueConfigTest extends TestCase
{
    private const POSITIONS = 20;
    private const EUR = 100; // cents per euro

    public function test_pool_total_matches_the_real_premier_league_broadcast_deal(): void
    {
        $total = 0;
        for ($pos = 1; $pos <= self::POSITIONS; $pos++) {
            $total += (new PremierLeagueConfig())->getTvRevenue($pos);
        }

        // ~€2.95B target; allow a tuning band of €2.8B–3.2B around the real deal.
        $this->assertGreaterThanOrEqual(2_800_000_000 * self::EUR, $total);
        $this->assertLessThanOrEqual(3_200_000_000 * self::EUR, $total);
    }

    public function test_distribution_is_flat_with_a_high_floor(): void
    {
        $config = new PremierLeagueConfig();
        $top = $config->getTvRevenue(1);
        $floor = $config->getTvRevenue(self::POSITIONS);

        // The PL floor must stay realistically high (≥ €100M) and the floor/top
        // ratio flat (≥ 0.50) — the real PL pays its bottom club ~60% of the top.
        $this->assertGreaterThanOrEqual(100_000_000 * self::EUR, $floor);
        $this->assertGreaterThanOrEqual(0.50, $floor / $top);
    }

    public function test_payout_is_monotonic_by_position(): void
    {
        $config = new PremierLeagueConfig();

        for ($pos = 1; $pos < self::POSITIONS; $pos++) {
            $this->assertGreaterThanOrEqual(
                $config->getTvRevenue($pos + 1),
                $config->getTvRevenue($pos),
                "Position {$pos} must not earn less than position " . ($pos + 1),
            );
        }
    }
}
