<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Modules\Stadium\Services\DemandCurveService;
use Tests\TestCase;

/**
 * Pure-formula coverage for the demand curve: loyalty drives fill, reputation
 * provides a secondary floor, marquee visitors sell the ground out, and the
 * gate is always capped at capacity. No database — the curve reads only the
 * passed models and config.
 */
class DemandCurveServiceTest extends TestCase
{
    private DemandCurveService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DemandCurveService();
    }

    public function test_loyalty_drives_fill_rate_monotonically(): void
    {
        $home = $this->team();
        $away = $this->rep('local', 0); // local visitor has no reputation floor
        $league = $this->league();

        $low = $this->service->project($home, $this->rep('local', 0), $away, $league, 10_000);
        $mid = $this->service->project($home, $this->rep('local', 50), $away, $league, 10_000);
        $high = $this->service->project($home, $this->rep('local', 100), $away, $league, 10_000);

        $this->assertLessThan($mid, $low);
        $this->assertLessThan($high, $mid);

        // loyalty 0 → 50% floor, loyalty 100 → 95%. Same-tier league opponent
        // contributes a tiny negative modifier, so allow a small band.
        $this->assertEqualsWithDelta(5_000, $low, 150);
        $this->assertEqualsWithDelta(9_500, $high, 150);
    }

    public function test_marquee_visitor_sells_out_regardless_of_loyalty(): void
    {
        $attendance = $this->service->project(
            $this->team(),
            $this->rep('local', 0),   // crashed home loyalty
            $this->rep('elite', 50),  // elite visitor
            $this->league(),
            10_000,
        );

        $this->assertSame(10_000, $attendance);
    }

    public function test_visitor_reputation_sets_a_floor_below_sellout(): void
    {
        // Continental visitor: 0.80 floor, but the context bonus stays under
        // the sellout threshold, so the floor (not capacity) binds.
        $attendance = $this->service->project(
            $this->team(),
            $this->rep('local', 0),
            $this->rep('continental', 50),
            $this->league(),
            10_000,
        );

        $this->assertSame(8_000, $attendance);
    }

    public function test_zero_capacity_returns_zero(): void
    {
        $attendance = $this->service->project(
            $this->team(),
            $this->rep('local', 80),
            $this->rep('local', 50),
            $this->league(),
            0,
        );

        $this->assertSame(0, $attendance);
    }

    public function test_baseline_ignores_opponent_and_caps_at_capacity(): void
    {
        $attendance = $this->service->projectBaseline($this->team(), $this->rep('local', 100), 10_000);

        $this->assertEqualsWithDelta(9_500, $attendance, 50);
        $this->assertLessThanOrEqual(10_000, $attendance);
    }

    private function team(): Team
    {
        $team = new Team();
        $team->stadium_seats = 10_000;

        return $team;
    }

    private function rep(string $level, int $loyalty): TeamReputation
    {
        $rep = new TeamReputation();
        $rep->reputation_level = $level;
        $rep->base_reputation_level = $level;
        $rep->loyalty_points = $loyalty;
        $rep->base_loyalty = $loyalty;

        return $rep;
    }

    private function league(): Competition
    {
        $competition = new Competition();
        $competition->role = Competition::ROLE_LEAGUE;
        $competition->handler_type = 'league';

        return $competition;
    }
}
