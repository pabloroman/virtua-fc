<?php

namespace Tests\Unit;

use App\Modules\Player\Services\DevelopmentCurve;
use App\Modules\Player\Services\PlayerDevelopmentService;
use Tests\TestCase;

class PlayerDevelopmentServiceTest extends TestCase
{
    private PlayerDevelopmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PlayerDevelopmentService::class);
    }

    public function test_generate_potential_respects_reachable_cap_for_developing_player(): void
    {
        // 22yo @ 80 with €50M: max reachable growth from 22 is 13 (5 base +
        // 8 bonus). High market value pushes potentialRange high enough that
        // pre-cap potential can exceed 93; the reachable cap clamps it.
        $cap = 80 + DevelopmentCurve::maxLifetimeGrowth(22);

        for ($i = 0; $i < 100; $i++) {
            $result = $this->service->generatePotential(age: 22, currentAbility: 80, marketValueCents: 5_000_000_000);

            $this->assertLessThanOrEqual($cap, $result['potential']);
            $this->assertLessThanOrEqual($cap, $result['high']);
            $this->assertGreaterThanOrEqual(80, $result['potential']);
        }
    }

    public function test_generate_potential_clamps_age_24_to_reachable_ceiling(): void
    {
        // 24yo @ 80: max reachable is 6 (2 base + 4 bonus).
        $cap = 80 + DevelopmentCurve::maxLifetimeGrowth(24);

        for ($i = 0; $i < 100; $i++) {
            $result = $this->service->generatePotential(age: 24, currentAbility: 80, marketValueCents: 1_000_000_000);

            $this->assertLessThanOrEqual($cap, $result['potential']);
            $this->assertLessThanOrEqual($cap, $result['high']);
        }
    }

    public function test_generate_potential_for_elite_young_prospect_can_reach_high_nineties(): void
    {
        // 16yo @ 70 with elite market value (€120M = 12_000_000_000 cents):
        // academy branch, valueBonus = 12. basePotentialRange ∈ [8, 20], so
        // potentialRange ∈ [20, 32] and truePotential lands in [90, 99] after
        // the 99 cap. The reachable cap (70 + 40 = 99) does not bite further.
        $maxObserved = 0;

        for ($i = 0; $i < 200; $i++) {
            $result = $this->service->generatePotential(age: 16, currentAbility: 70, marketValueCents: 12_000_000_000);

            $this->assertGreaterThanOrEqual(90, $result['potential']);
            $this->assertLessThanOrEqual(99, $result['potential']);
            $maxObserved = max($maxObserved, $result['potential']);
        }

        // Across 200 rolls the elite path should reliably produce a 95+ peak.
        $this->assertGreaterThanOrEqual(95, $maxObserved);
    }

    public function test_generate_potential_for_modest_young_player_stays_realistic(): void
    {
        // 17yo @ 60 with no proven market value — academy branch, valueBonus=0.
        // basePotentialRange ∈ [8, 20], so truePotential lands in [68, 80].
        for ($i = 0; $i < 100; $i++) {
            $result = $this->service->generatePotential(age: 17, currentAbility: 60, marketValueCents: 0);

            $this->assertGreaterThanOrEqual(68, $result['potential']);
            $this->assertLessThanOrEqual(80, $result['potential']);
        }
    }

    public function test_generate_potential_for_veteran_returns_current_ability(): void
    {
        // Veterans branch returns potentialRange = 0 — current ability IS
        // the ceiling.
        $result = $this->service->generatePotential(age: 33, currentAbility: 82, marketValueCents: 5_000_000_00);

        $this->assertSame(82, $result['potential']);
        $this->assertSame(82, $result['low']);
        $this->assertSame(82, $result['high']);
    }
}
