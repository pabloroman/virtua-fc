<?php

namespace Tests\Unit;

use App\Modules\Match\Support\MatchOutcomeModel;
use PHPUnit\Framework\TestCase;

class MatchOutcomeModelApplyFloorTest extends TestCase
{
    public function test_zero_floor_is_a_no_op(): void
    {
        $this->assertSame(0.80, MatchOutcomeModel::applyFloor(0.80, 0.0));
        $this->assertSame(0.55, MatchOutcomeModel::applyFloor(0.55, 0.0));
    }

    public function test_out_of_range_floor_is_a_no_op(): void
    {
        $this->assertSame(0.80, MatchOutcomeModel::applyFloor(0.80, -5.0));
        $this->assertSame(0.80, MatchOutcomeModel::applyFloor(0.80, 100.0));
        $this->assertSame(0.80, MatchOutcomeModel::applyFloor(0.80, 120.0));
    }

    public function test_rescale_matches_formula(): void
    {
        // rating 80, floor 50 → (80-50)/(100-50) = 0.60
        $this->assertEqualsWithDelta(0.60, MatchOutcomeModel::applyFloor(0.80, 50.0), 1e-9);
        // rating 64, floor 50 → (64-50)/(100-50) = 0.28
        $this->assertEqualsWithDelta(0.28, MatchOutcomeModel::applyFloor(0.64, 50.0), 1e-9);
    }

    public function test_floor_widens_the_ratio_between_two_teams(): void
    {
        $strong = 0.80; // rating 80
        $weak = 0.64;   // rating 64

        $rawRatio = $strong / $weak; // 1.25
        $flooredStrong = MatchOutcomeModel::applyFloor($strong, 50.0); // 0.60
        $flooredWeak = MatchOutcomeModel::applyFloor($weak, 50.0);     // 0.28
        $flooredRatio = $flooredStrong / $flooredWeak;                 // ~2.14

        $this->assertGreaterThan($rawRatio, $flooredRatio);
        $this->assertEqualsWithDelta(2.142857, $flooredRatio, 1e-4);
    }

    public function test_strength_at_or_below_floor_is_clamped_positive(): void
    {
        // rating 40, floor 50 → negative pre-clamp; must stay strictly positive
        $result = MatchOutcomeModel::applyFloor(0.40, 50.0);
        $this->assertGreaterThan(0.0, $result);
        $this->assertSame(0.02, $result);
    }
}
