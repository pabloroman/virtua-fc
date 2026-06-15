<?php

namespace Tests\Unit;

use App\Modules\Player\Services\DevelopmentCurve;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DevelopmentCurveTest extends TestCase
{
    public function test_gap_bonus_returns_two_for_large_gap_during_growth_window(): void
    {
        $this->assertSame(2, DevelopmentCurve::gapBonus(16, 60, 95));
        $this->assertSame(2, DevelopmentCurve::gapBonus(25, 60, 95));
    }

    public function test_gap_bonus_returns_one_for_moderate_gap(): void
    {
        $this->assertSame(1, DevelopmentCurve::gapBonus(20, 70, 89));
        // Exactly at the +1 threshold.
        $this->assertSame(1, DevelopmentCurve::gapBonus(20, 70, 85));
    }

    public function test_gap_bonus_returns_zero_for_small_gap(): void
    {
        $this->assertSame(0, DevelopmentCurve::gapBonus(20, 80, 89));
        $this->assertSame(0, DevelopmentCurve::gapBonus(20, 80, 80));
    }

    public function test_gap_bonus_returns_zero_after_growth_window(): void
    {
        $this->assertSame(0, DevelopmentCurve::gapBonus(26, 60, 99));
        $this->assertSame(0, DevelopmentCurve::gapBonus(30, 60, 99));
    }

    public function test_gap_bonus_thresholds_are_exclusive_at_24(): void
    {
        // gap = 24 → +1 (not yet at tier-2 threshold of 25)
        $this->assertSame(1, DevelopmentCurve::gapBonus(20, 70, 94));
        // gap = 25 → +2
        $this->assertSame(2, DevelopmentCurve::gapBonus(20, 70, 95));
    }

    #[DataProvider('maxLifetimeGrowthCases')]
    public function test_max_lifetime_growth_matches_curve_plus_bonus(int $age, int $expected): void
    {
        $this->assertSame($expected, DevelopmentCurve::maxLifetimeGrowth($age));
    }

    public static function maxLifetimeGrowthCases(): array
    {
        return [
            '16yo: 20 base + 20 bonus' => [16, 40],
            '17yo: 17 base + 18 bonus' => [17, 35],
            '18yo: 14 base + 16 bonus' => [18, 30],
            '19yo: 11 base + 14 bonus' => [19, 25],
            '20yo: 9 base + 12 bonus' => [20, 21],
            '21yo: 7 base + 10 bonus' => [21, 17],
            '22yo: 5 base + 8 bonus' => [22, 13],
            '23yo: 3 base + 6 bonus' => [23, 9],
            '24yo: 2 base + 4 bonus' => [24, 6],
            '25yo: 1 base + 2 bonus' => [25, 3],
            '26yo: plateau, no growth' => [26, 0],
            '30yo: declining, no growth' => [30, 0],
        ];
    }

    public function test_age_curve_grows_through_25_and_plateaus_at_26(): void
    {
        // Growth phase
        $this->assertGreaterThan(0, DevelopmentCurve::getChange(25));
        // Plateau
        $this->assertSame(0, DevelopmentCurve::getChange(26));
        $this->assertSame(0, DevelopmentCurve::getChange(27));
        // Decline
        $this->assertLessThan(0, DevelopmentCurve::getChange(28));
    }
}
