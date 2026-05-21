<?php

namespace Tests\Unit;

use App\Modules\Competition\Services\CalendarService;
use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Modules\Lineup\Services\AITacticsService;
use App\Modules\Lineup\Services\FormationBiasResolver;
use App\Modules\Lineup\Services\FormationRecommender;
use PHPUnit\Framework\TestCase;

class AIMentalityBiasTest extends TestCase
{
    private AITacticsService $aiTactics;

    protected function setUp(): void
    {
        parent::setUp();
        // selectAIMentality and selectAIInstructions are pure functions of
        // their inputs; they do not touch the injected collaborators, so
        // raw instances are fine for this unit slice.
        $this->aiTactics = new AITacticsService(
            new FormationRecommender(),
            new FormationBiasResolver(),
            $this->createMock(CalendarService::class),
        );
    }

    public function test_zero_bias_preserves_existing_baseline(): void
    {
        // Elite team at home vs similar-strength opponent → BALANCED was the
        // documented baseline before bias was introduced.
        $mentality = $this->aiTactics->selectAIMentality('elite', true, 80, 80, 0);
        $this->assertSame(Mentality::BALANCED, $mentality);
    }

    public function test_negative_bias_pushes_baseline_toward_defensive(): void
    {
        // -2 should ladder-shift BALANCED two steps down → DEFENSIVE.
        $mentality = $this->aiTactics->selectAIMentality('elite', true, 80, 80, -2);
        $this->assertSame(Mentality::DEFENSIVE, $mentality);
    }

    public function test_positive_bias_pushes_baseline_toward_attacking(): void
    {
        // +1 shifts a 'mid'-tier away vs similar (DEFENSIVE) → BALANCED.
        $balanced = $this->aiTactics->selectAIMentality('continental', false, 75, 75, 1);
        $this->assertSame(Mentality::BALANCED, $balanced);

        // +2 from the same baseline lands on ATTACKING (clamped at top).
        $attacking = $this->aiTactics->selectAIMentality('continental', false, 75, 75, 2);
        $this->assertSame(Mentality::ATTACKING, $attacking);
    }

    public function test_bias_clamps_at_ladder_bounds(): void
    {
        // Already DEFENSIVE — shifting further down stays DEFENSIVE rather
        // than walking off the ladder.
        $stillDefensive = $this->aiTactics->selectAIMentality('local', false, 60, 75, -2);
        $this->assertSame(Mentality::DEFENSIVE, $stillDefensive);

        // Already ATTACKING — extra positive bias caps at ATTACKING.
        $stillAttacking = $this->aiTactics->selectAIMentality('elite', true, 85, 75, 2);
        $this->assertSame(Mentality::ATTACKING, $stillAttacking);
    }

    public function test_null_reputation_short_circuits_to_balanced_and_ignores_bias(): void
    {
        // The early-return guards inputs we cannot reason about — bias must
        // not push a guarded "we don't know" into a confident extreme.
        $result = $this->aiTactics->selectAIMentality(null, true, 75, 75, 2);
        $this->assertSame(Mentality::BALANCED, $result);
    }

    public function test_aggressive_bias_lifts_pressing_and_line_and_style(): void
    {
        // Mid-tier team at home vs similar (baseline BALANCED / STANDARD /
        // NORMAL). +2 bias should walk every dimension up the ladder.
        [$style, $pressing, $defLine] = $this->aiTactics->selectAIInstructions(
            'continental',
            true,
            75,
            75,
            2,
        );

        $this->assertSame(PlayingStyle::POSSESSION, $style);
        $this->assertSame(PressingIntensity::HIGH_PRESS, $pressing);
        $this->assertSame(DefensiveLineHeight::HIGH_LINE, $defLine);
    }

    public function test_cautious_bias_drops_pressing_and_line_and_style(): void
    {
        // Same baseline as above, but -2 bias — every dimension to the
        // defensive end of its ladder.
        [$style, $pressing, $defLine] = $this->aiTactics->selectAIInstructions(
            'continental',
            true,
            75,
            75,
            -2,
        );

        $this->assertSame(PlayingStyle::COUNTER_ATTACK, $style);
        $this->assertSame(PressingIntensity::LOW_BLOCK, $pressing);
        $this->assertSame(DefensiveLineHeight::DEEP, $defLine);
    }
}
