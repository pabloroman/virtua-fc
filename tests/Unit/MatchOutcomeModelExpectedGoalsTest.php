<?php

namespace Tests\Unit;

use App\Modules\Match\Support\MatchOutcomeModel;
use Tests\TestCase;

/**
 * The difference-based goal-supremacy model: xG is driven by the rating-point
 * gap between the two sides, split evenly around the even-match baseline. There
 * is no ratio (so no floor and no clamp) — a bigger gap keeps widening the xG
 * gap instead of being renormalised.
 */
class MatchOutcomeModelExpectedGoalsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('match_simulation.base_goals', 1.4);
        config()->set('match_simulation.goal_supremacy_scale', 10.0);
        config()->set('match_simulation.home_advantage_goals', 0.20);
    }

    public function test_evenly_matched_teams_get_base_goals_on_a_neutral_venue(): void
    {
        [$home, $away] = MatchOutcomeModel::expectedGoals(0.80, 0.80, neutralVenue: true);

        $this->assertEqualsWithDelta(1.4, $home, 1e-9);
        $this->assertEqualsWithDelta(1.4, $away, 1e-9);
    }

    public function test_home_advantage_lifts_only_the_home_side(): void
    {
        [$home, $away] = MatchOutcomeModel::expectedGoals(0.80, 0.80, neutralVenue: false);

        $this->assertEqualsWithDelta(1.6, $home, 1e-9);
        $this->assertEqualsWithDelta(1.4, $away, 1e-9);
    }

    public function test_supremacy_is_the_rating_gap_over_the_scale(): void
    {
        // gap = (0.92 - 0.80) * 100 = 12 rating points; supremacy = 12/10 = 1.2,
        // split ±0.6 around base 1.4 → 2.0 / 0.8 (neutral).
        [$home, $away] = MatchOutcomeModel::expectedGoals(0.92, 0.80, neutralVenue: true);

        $this->assertEqualsWithDelta(2.0, $home, 1e-9);
        $this->assertEqualsWithDelta(0.8, $away, 1e-9);
    }

    public function test_a_bigger_gap_keeps_widening_the_xg_gap_no_ratio_cap(): void
    {
        [, $awaySmall] = MatchOutcomeModel::expectedGoals(0.86, 0.80, neutralVenue: true);
        [$homeBig] = MatchOutcomeModel::expectedGoals(0.95, 0.80, neutralVenue: true);
        [$homeBigger] = MatchOutcomeModel::expectedGoals(0.99, 0.80, neutralVenue: true);

        // Monotonic: a dominant squad pulls clear rather than being renormalised.
        $this->assertGreaterThan($awaySmall, $homeBig);
        $this->assertGreaterThan($homeBig, $homeBigger);
    }

    public function test_the_weaker_side_is_floored_but_never_negative_on_a_lopsided_gap(): void
    {
        // gap = 40 pts → supremacy 4.0 → away would be 1.4 - 2.0 = -0.6, floored.
        [$home, $away] = MatchOutcomeModel::expectedGoals(0.95, 0.55, neutralVenue: true);

        $this->assertGreaterThan(0.0, $away);
        $this->assertLessThanOrEqual(0.2, $away);
        // No explosion: even a huge gap keeps the favourite's xG sane (no ratio
        // raised to a power), unlike the old ratio model.
        $this->assertLessThan(5.0, $home);
    }

    public function test_a_non_positive_scale_collapses_to_an_even_match(): void
    {
        [$home, $away] = MatchOutcomeModel::expectedGoals(
            0.92,
            0.80,
            neutralVenue: true,
            overrides: ['goal_supremacy_scale' => 0.0],
        );

        $this->assertEqualsWithDelta(1.4, $home, 1e-9);
        $this->assertEqualsWithDelta(1.4, $away, 1e-9);
    }
}
