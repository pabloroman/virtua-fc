<?php

namespace Tests\Unit;

use App\Models\ClubProfile;
use App\Modules\Lineup\Services\FormationBiasResolver;
use PHPUnit\Framework\TestCase;

class FormationBiasResolverTest extends TestCase
{
    private FormationBiasResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new FormationBiasResolver();
    }

    public function test_tier_fallback_returns_a_ranked_pool(): void
    {
        $bias = $this->resolver->biasForTier(ClubProfile::REPUTATION_LOCAL);

        $this->assertCount(3, $bias, 'Local-tier pool should expose three formations');

        $bonuses = array_values($bias);
        $this->assertGreaterThan($bonuses[1], $bonuses[0], 'Top of the pool should win when scoring ties');
        $this->assertSame($bonuses[1], $bonuses[2], 'Runners-up share the same softer bonus');
    }

    public function test_local_tier_skews_toward_compact_shapes(): void
    {
        $bias = $this->resolver->biasForTier(ClubProfile::REPUTATION_LOCAL);

        // Local sides should not be biased toward attacking 4-3-3; that's
        // the giveaway that the AI used to default everyone to 4-3-3.
        $this->assertArrayNotHasKey('4-3-3', $bias);
        $this->assertArrayHasKey('4-4-2', $bias);
    }

    public function test_elite_tier_skews_toward_attacking_shapes(): void
    {
        $bias = $this->resolver->biasForTier(ClubProfile::REPUTATION_ELITE);

        $this->assertArrayHasKey('4-3-3', $bias);
        $this->assertArrayNotHasKey('5-3-2', $bias);
        $this->assertArrayNotHasKey('5-4-1', $bias);
    }

    public function test_unknown_tier_falls_back_to_local_pool(): void
    {
        $bias = $this->resolver->biasForTier('not_a_real_tier');

        $this->assertSame(
            $this->resolver->biasForTier(ClubProfile::REPUTATION_LOCAL),
            $bias,
            'Unknown tiers should reuse the safest local pool',
        );
    }
}
