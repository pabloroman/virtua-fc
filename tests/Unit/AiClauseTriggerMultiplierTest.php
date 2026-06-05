<?php

namespace Tests\Unit;

use App\Modules\Transfer\Services\TransferService;
use Tests\TestCase;

/**
 * Pure-math coverage for the AI release-clause poach boost. A clause that has
 * fallen below the player's current market value is a bargain forced-buy, so the
 * per-matchday trigger chance is scaled up by
 * {@see TransferService::underpricedClauseTriggerMultiplier}. No DB needed — the
 * multiplier is a pure function of (market value, clause) given the pinned config.
 */
class AiClauseTriggerMultiplierTest extends TestCase
{
    private TransferService $service;

    private const MV = 5_000_000_000; // €50M market value, in cents

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TransferService::class);

        config()->set('finances.release_clause.ai_underprice_slope', 1.0);
        config()->set('finances.release_clause.ai_underprice_max_multiplier', 5.0);
    }

    public function test_clause_at_market_value_gets_no_boost(): void
    {
        // ratio = 1.0 → not underpriced → multiplier 1.0.
        $this->assertSame(1.0, $this->service->underpricedClauseTriggerMultiplier(self::MV, self::MV));
    }

    public function test_clause_above_market_value_gets_no_boost(): void
    {
        // The fresh ES floor (1.25× MV) sits above market value → ratio 0.8 → 1.0.
        $floor = 6_250_000_000;
        $this->assertSame(1.0, $this->service->underpricedClauseTriggerMultiplier(self::MV, $floor));

        // A Phase-4 raised clause (well above MV) is likewise never reduced — still 1.0.
        $this->assertSame(1.0, $this->service->underpricedClauseTriggerMultiplier(self::MV, 2 * self::MV));
    }

    public function test_underpriced_clause_scales_linearly_with_the_ratio(): void
    {
        // Clause at half of market value → ratio 2.0 → 1 + 1.0 × (2 − 1) = 2.0.
        $this->assertSame(2.0, $this->service->underpricedClauseTriggerMultiplier(self::MV, self::MV / 2));

        // Clause at a quarter → ratio 4.0 → 1 + 3 = 4.0.
        $this->assertSame(4.0, $this->service->underpricedClauseTriggerMultiplier(self::MV, self::MV / 4));
    }

    public function test_a_wildly_underpriced_clause_is_capped(): void
    {
        // Clause at a tenth → ratio 10 → 1 + 9 = 10, capped at the max (5.0).
        $this->assertSame(5.0, $this->service->underpricedClauseTriggerMultiplier(self::MV, self::MV / 10));
    }

    public function test_zero_values_are_safe_and_unboosted(): void
    {
        // Guards against divide-by-zero / nonsense data — no boost.
        $this->assertSame(1.0, $this->service->underpricedClauseTriggerMultiplier(self::MV, 0));
        $this->assertSame(1.0, $this->service->underpricedClauseTriggerMultiplier(0, self::MV));
    }
}
