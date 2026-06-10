<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Modules\Stadium\Services\FanLoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the loyalty seed scaling and the one-off delta clamp — in
 * particular the "base − 15" floor that keeps a high-loyalty club from
 * emptying its stadium after a run of bad seasons (or a naming-rights shock).
 */
class FanLoyaltyServiceTest extends TestCase
{
    use RefreshDatabase;

    private FanLoyaltyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FanLoyaltyService();
    }

    public function test_seed_scales_the_curated_anchor_to_the_internal_range(): void
    {
        $this->assertSame(80, $this->service->seedInitialValue(8));
        $this->assertSame(50, $this->service->seedInitialValue(null)); // default 5 × 10
        $this->assertSame(100, $this->service->seedInitialValue(15));  // clamped to 10 × 10
        $this->assertSame(0, $this->service->seedInitialValue(-3));    // clamped to 0
    }

    public function test_apply_delta_respects_the_base_floor(): void
    {
        $rep = $this->rep(base: 60, current: 60);

        // Floor is base − MAX_LOYALTY_DROP_BELOW_BASE (15) = 45; a −30 hit
        // can't drop loyalty below it.
        $this->service->applyDelta($rep, -30);

        $this->assertSame(45, $rep->fresh()->loyalty_points);
    }

    public function test_apply_delta_caps_at_max(): void
    {
        $rep = $this->rep(base: 60, current: 95);

        $this->service->applyDelta($rep, 20);

        $this->assertSame(TeamReputation::LOYALTY_MAX, $rep->fresh()->loyalty_points);
    }

    private function rep(int $base, int $current): TeamReputation
    {
        $team = Team::factory()->create();
        $game = Game::factory()->forTeam($team)->create();

        return TeamReputation::create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'reputation_level' => 'modest',
            'base_reputation_level' => 'modest',
            'reputation_points' => TeamReputation::pointsForTier('modest'),
            'base_loyalty' => $base,
            'loyalty_points' => $current,
        ]);
    }
}
