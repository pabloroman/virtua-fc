<?php

namespace Tests\Unit;

use App\Modules\Transfer\Services\ContractService;
use App\Support\Money;
use Tests\TestCase;

/**
 * Pure-math coverage for the release-clause formulas on ContractService. No DB
 * needed — these are deterministic given the config multipliers, which are
 * pinned in setUp() so the assertions survive balance tweaks to
 * config/finances.php.
 *
 * The manager-set clause is no longer capped: above the mandatory floor it is
 * honoured as-is (calculateReleaseClause), and the cost is paid in wages — a
 * clause above the floor raises the player's wage demand
 * (effectiveDemandWithReleaseClause).
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
        config()->set('finances.release_clause.tolerance.premium_slope', 2.5);
    }

    public function test_es_club_defaults_to_the_floor(): void
    {
        // €50M × 1.25 = €62.5M
        $this->assertSame(6_250_000_000, $this->service->calculateReleaseClause(self::MV, 'ES'));
    }

    public function test_non_es_club_has_no_clause_by_default(): void
    {
        $this->assertNull($this->service->calculateReleaseClause(self::MV, 'EN'));
        $this->assertNull($this->service->calculateReleaseClause(self::MV, null));
    }

    public function test_zero_market_value_yields_no_clause_even_for_es(): void
    {
        // Avoids a €0 buyout for valueless players.
        $this->assertNull($this->service->calculateReleaseClause(0, 'ES'));
    }

    public function test_user_request_below_floor_is_clamped_up_to_the_floor(): void
    {
        // €10M request < €62.5M floor → clamped up to the floor.
        $this->assertSame(
            6_250_000_000,
            $this->service->calculateReleaseClause(self::MV, 'ES', 1_000_000_000),
        );
    }

    public function test_user_request_above_the_floor_is_honoured_unclamped(): void
    {
        // No upper cap any more: a €100M request well above the €62.5M floor is
        // stored exactly as asked. The golden-handcuffs cost is paid in wages
        // during negotiation, not capped here.
        $this->assertSame(
            10_000_000_000,
            $this->service->calculateReleaseClause(self::MV, 'ES', 10_000_000_000),
        );
    }

    public function test_non_es_club_can_opt_in_to_a_clause(): void
    {
        // Optional elsewhere: a non-ES club that explicitly requests a clause
        // gets one, floored at the mandatory minimum but otherwise honoured.
        $this->assertSame(
            10_000_000_000,
            $this->service->calculateReleaseClause(self::MV, 'EN', 10_000_000_000),
        );
    }

    public function test_clause_is_snapped_to_a_nice_round_number(): void
    {
        // Regression: an un-round market value (€453,340.43) used to yield an
        // un-round €566,675.54 floor. The floor is now snapped via
        // Money::roundPrice — €453,340 × 1.25 = €566,675 → nearest €50K = €550K.
        $marketValue = 45_334_043; // cents

        $clause = $this->service->calculateReleaseClause($marketValue, 'ES');

        $this->assertSame(55_000_000, $clause);
        $this->assertSame(Money::roundPrice($clause), $clause, 'Clause must already be a round price.');
    }

    public function test_clause_at_or_below_floor_leaves_the_wage_demand_untouched(): void
    {
        // A floor-level clause is the default — no golden-handcuffs premium, so the
        // player's wage demand is exactly the base ask. Below-floor behaves the same.
        $baseDemand = 1_000_000_000; // €10M
        $floor = 6_250_000_000;

        $this->assertSame($baseDemand, $this->service->effectiveDemandWithReleaseClause($baseDemand, self::MV, $floor, 'ES'));
        $this->assertSame($baseDemand, $this->service->effectiveDemandWithReleaseClause($baseDemand, self::MV, $floor - 1, 'ES'));
        $this->assertSame($baseDemand, $this->service->effectiveDemandWithReleaseClause($baseDemand, self::MV, null, 'ES'));
    }

    public function test_clause_above_the_floor_raises_the_wage_demand(): void
    {
        // floor = €62.5M. A clause one full (slope × MV) above the floor — here
        // €62.5M + 2.5 × €50M = €187.5M — doubles the demand:
        //   factor = 1 + (187.5M − 62.5M) / (2.5 × 50M) = 1 + 125M/125M = 2.0
        $baseDemand = 1_000_000_000; // €10M
        $clause = 18_750_000_000;    // €187.5M

        $this->assertSame(
            2_000_000_000, // €20M
            $this->service->effectiveDemandWithReleaseClause($baseDemand, self::MV, $clause, 'ES'),
        );
    }

    public function test_non_es_club_ignores_the_clause_for_the_wage_demand(): void
    {
        // The golden-handcuffs wage premium only exists in mandatory-clause
        // countries; elsewhere the clause never touches the wage demand.
        $baseDemand = 1_000_000_000;
        $clause = 18_750_000_000;

        $this->assertSame($baseDemand, $this->service->effectiveDemandWithReleaseClause($baseDemand, self::MV, $clause, 'EN'));
    }
}
