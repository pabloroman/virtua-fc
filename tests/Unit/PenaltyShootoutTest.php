<?php

namespace Tests\Unit;

use App\Modules\Match\Services\MatchSimulator;
use ReflectionMethod;
use Tests\TestCase;

class PenaltyShootoutTest extends TestCase
{
    private MatchSimulator $simulator;

    private ReflectionMethod $hasPenaltyShootoutWinner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->simulator = new MatchSimulator;
        $this->hasPenaltyShootoutWinner = new ReflectionMethod(MatchSimulator::class, 'hasPenaltyShootoutWinner');
    }

    public function test_shootout_can_end_before_last_away_kick_when_result_is_decided(): void
    {
        $resolved = $this->hasPenaltyShootoutWinner->invoke(
            $this->simulator,
            4,
            2,
            5,
            4,
            5,
        );

        $this->assertTrue($resolved);
    }

    public function test_shootout_continues_when_trailing_team_can_still_equalise(): void
    {
        $resolved = $this->hasPenaltyShootoutWinner->invoke(
            $this->simulator,
            3,
            2,
            4,
            4,
            5,
        );

        $this->assertFalse($resolved);
    }

    public function test_shootout_can_end_immediately_after_home_kick_in_round_five(): void
    {
        $resolved = $this->hasPenaltyShootoutWinner->invoke(
            $this->simulator,
            5,
            3,
            5,
            4,
            5,
        );

        $this->assertTrue($resolved);
    }
}
