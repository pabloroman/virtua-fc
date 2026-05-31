<?php

namespace Tests\Unit;

use App\Modules\Transfer\Services\ContractService;
use Tests\TestCase;

/**
 * Pure-math coverage for the "golden handcuffs" release-clause formula on
 * ContractService. No DB needed — these are deterministic given the config
 * multipliers, which are pinned in setUp() so the assertions survive balance
 * tweaks to config/finances.php.
 */
class ReleaseClauseCalculationTest extends TestCase
{
    private ContractService $service;

    private const MV = 5_000_000_000; // €50M market value, in cents

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ContractService::class);

        // Pin the curve so the maths below stays deterministic.
        config()->set('finances.release_clause.es_floor_multiplier', 1.25);
        config()->set('finances.release_clause.tolerance.base', 1.25);
        config()->set('finances.release_clause.tolerance.premium_slope', 2.5);
        config()->set('finances.release_clause.tolerance.hard_cap', 2.5);
    }

    public function test_es_club_defaults_to_the_floor(): void
    {
        // €50M × 1.25 = €62.5M
        $this->assertSame(6_250_000_000, $this->service->calculateReleaseClause(self::MV, null, null, 'ES'));
    }

    public function test_non_es_club_has_no_clause_by_default(): void
    {
        $this->assertNull($this->service->calculateReleaseClause(self::MV, null, null, 'EN'));
        $this->assertNull($this->service->calculateReleaseClause(self::MV, null, null, null));
    }

    public function test_zero_market_value_yields_no_clause_even_for_es(): void
    {
        // Avoids a €0 buyout for valueless players.
        $this->assertNull($this->service->calculateReleaseClause(0, null, null, 'ES'));
    }

    public function test_user_request_below_floor_is_clamped_up_to_the_floor(): void
    {
        // €10M request < €62.5M floor → clamped up to the floor.
        $this->assertSame(
            6_250_000_000,
            $this->service->calculateReleaseClause(self::MV, null, null, 'ES', 1_000_000_000),
        );
    }

    public function test_user_request_above_tolerance_is_clamped_down(): void
    {
        // At wage parity the cap is base × MV = €62.5M, so a €100M request
        // without a wage premium is clamped down to €62.5M.
        $this->assertSame(
            6_250_000_000,
            $this->service->calculateReleaseClause(self::MV, null, null, 'ES', 10_000_000_000),
        );
    }

    public function test_wage_premium_raises_the_ceiling_and_request_is_honoured(): void
    {
        // Offered wage 1.5× the demand → premium 0.5 → cap = min(2.5, 1.25 +
        // 2.5 × 0.5) = 2.5 → €125M ceiling. A €100M request sits inside the
        // [€62.5M, €125M] band and is honoured exactly.
        $this->assertSame(
            10_000_000_000,
            $this->service->calculateReleaseClause(self::MV, 1_500_000_000, 1_000_000_000, 'ES', 10_000_000_000),
        );
    }

    public function test_non_es_club_can_opt_in_to_a_clause(): void
    {
        // Optional elsewhere: a non-ES club that explicitly requests a clause
        // gets one, clamped into the tolerated band.
        $this->assertSame(
            10_000_000_000,
            $this->service->calculateReleaseClause(self::MV, 1_500_000_000, 1_000_000_000, 'EN', 10_000_000_000),
        );
    }

    public function test_max_tolerable_defaults_to_wage_parity(): void
    {
        // base × MV = €62.5M when wages are unknown or at parity.
        $this->assertSame(6_250_000_000, $this->service->maxTolerableReleaseClause(self::MV, null, null));
        $this->assertSame(6_250_000_000, $this->service->maxTolerableReleaseClause(self::MV, 1_000_000_000, 1_000_000_000));
    }

    public function test_max_tolerable_is_hard_capped(): void
    {
        // A 10× wage offer would imply a huge multiple but the hard cap pins it
        // at 2.5 × MV = €125M.
        $this->assertSame(
            12_500_000_000,
            $this->service->maxTolerableReleaseClause(self::MV, 10_000_000_000, 1_000_000_000),
        );
    }
}
