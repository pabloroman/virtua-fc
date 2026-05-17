<?php

namespace Tests\Unit;

use App\Models\ClubProfile;
use App\Modules\Manager\ManagerReputation;
use App\Modules\Manager\Services\ManagerReputationService;
use Tests\TestCase;

/**
 * Pure-logic tests for the per-season reputation delta and the
 * points→tier→anchor mapping that JobOfferService relies on. Kept
 * DB-free so the calibration table the rest of the offer system
 * trusts can be locked down without spinning up factories.
 */
class ManagerReputationServiceTest extends TestCase
{
    private ManagerReputationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ManagerReputationService();
    }

    /**
     * Calibration anchor: a brand-new manager's first promotion in Tier 3
     * should land them above the local→modest threshold within two seasons.
     * If this drops, the early-career progression has regressed.
     */
    public function test_exceeded_grade_with_promotion_yields_a_meaningful_jump(): void
    {
        $delta = $this->service->computeDelta([
            'grade' => 'exceeded',
            'promoted' => true,
            'trophyBoostSteps' => 0,
        ]);

        $this->assertSame(35, $delta);
    }

    public function test_exceptional_grade_with_european_trophy_hits_seasonal_cap(): void
    {
        // Champions League win = 2 boost steps (per SeasonGoalService weighting).
        $delta = $this->service->computeDelta([
            'grade' => 'exceptional',
            'promoted' => false,
            'trophyBoostSteps' => 2,
        ]);

        // 35 (exceptional) + 24 (2 steps × 12) = 59 — below MAX_SEASONAL_GAIN.
        $this->assertSame(59, $delta);
    }

    public function test_perfect_season_is_capped_so_no_two_tier_jump_in_one_year(): void
    {
        // Promoted + exceptional + UCL win — the most lopsided season possible.
        $delta = $this->service->computeDelta([
            'grade' => 'exceptional',
            'promoted' => true,
            'trophyBoostSteps' => 4,
        ]);

        $this->assertSame(ManagerReputation::MAX_SEASONAL_GAIN, $delta);
    }

    public function test_disaster_loss_is_bounded_so_one_bad_year_does_not_undo_a_career(): void
    {
        $delta = $this->service->computeDelta([
            'grade' => 'disaster',
            'promoted' => false,
            'trophyBoostSteps' => 0,
        ]);

        $this->assertSame(-ManagerReputation::MAX_SEASONAL_LOSS, $delta);
    }

    public function test_met_seasons_drift_upward_so_steady_managers_are_not_stuck(): void
    {
        $delta = $this->service->computeDelta([
            'grade' => 'met',
            'promoted' => false,
            'trophyBoostSteps' => 0,
        ]);

        $this->assertSame(5, $delta);
    }

    /**
     * @dataProvider tierBoundaryProvider
     */
    public function test_points_map_to_the_expected_tier(int $points, string $expected): void
    {
        $this->assertSame($expected, ManagerReputation::levelFromPoints($points));
    }

    public static function tierBoundaryProvider(): array
    {
        return [
            'zero is local'              => [0, ClubProfile::REPUTATION_LOCAL],
            'just-under-modest is local' => [99, ClubProfile::REPUTATION_LOCAL],
            'exactly modest'             => [100, ClubProfile::REPUTATION_MODEST],
            'exactly established'        => [200, ClubProfile::REPUTATION_ESTABLISHED],
            'exactly continental'        => [300, ClubProfile::REPUTATION_CONTINENTAL],
            'exactly elite'              => [400, ClubProfile::REPUTATION_ELITE],
            'above elite stays elite'    => [9_999, ClubProfile::REPUTATION_ELITE],
        ];
    }

    /**
     * The anchor mapping is what JobOfferService trusts to keep
     * low-tier managers from instantly fielding top-flight offers
     * and to lift continental-grade managers into Tier-1 pools.
     */
    public function test_local_manager_anchor_does_not_force_top_flight_offers(): void
    {
        $this->assertSame([3, ClubProfile::REPUTATION_LOCAL], ManagerReputation::anchorFor(ClubProfile::REPUTATION_LOCAL));
    }

    public function test_continental_manager_anchor_floors_at_top_flight(): void
    {
        [$tier] = ManagerReputation::anchorFor(ClubProfile::REPUTATION_CONTINENTAL);
        $this->assertSame(1, $tier);
    }

    public function test_elite_manager_anchor_floors_at_top_flight_continental(): void
    {
        $this->assertSame(
            [1, ClubProfile::REPUTATION_CONTINENTAL],
            ManagerReputation::anchorFor(ClubProfile::REPUTATION_ELITE),
        );
    }
}
