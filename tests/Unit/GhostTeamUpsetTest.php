<?php

namespace Tests\Unit;

use App\Modules\Match\Support\GhostStrength;
use App\Modules\Match\Support\MatchOutcomeModel;
use Tests\TestCase;

/**
 * Cup ties against a squad-less side, sampled through the production outcome
 * math with fixed strengths — the same approach GoalScoringBalanceTest takes,
 * so these are fast and need no database.
 *
 * Two things were wrong before. A ghost's rating was one constant, so a
 * second-tier club with 30,000 seats was exactly as hard to beat as a village
 * side. And it was charged twice for having no squad: once through the rating
 * gap, then again through the missing-goalkeeper penalty, which doubled the
 * opponent's xG on top of it. A user's side faced 8.87 xG and won these ties
 * about 6-0.
 */
class GhostTeamUpsetTest extends TestCase
{
    /** A ~75-rated XI once energy has eroded it, i.e. an ordinary top-flight side. */
    private const TOP_FLIGHT = 0.68;

    private const RUNS = 20000;

    public function test_a_tie_against_a_ghost_is_no_longer_a_rout(): void
    {
        [$userXG] = $this->expectedGoals('local');

        // Comfortable, not absurd. It used to be 8.87.
        $this->assertGreaterThan(2.0, $userXG);
        $this->assertLessThan(5.0, $userXG);
    }

    public function test_a_better_reputed_ghost_is_harder_to_beat(): void
    {
        $conceded = [];
        foreach (GhostStrength::levels() as $level) {
            [$userXG] = $this->expectedGoals($level);
            $conceded[$level] = $userXG;
        }

        $ordered = array_values($conceded);
        $sorted = $ordered;
        rsort($sorted);

        $this->assertSame(
            $sorted,
            $ordered,
            'reputation is ranked local to elite, so each tier should concede no more than the one below it',
        );
        $this->assertGreaterThan(
            0.5,
            reset($ordered) - end($ordered),
            'the tiers should be far enough apart to be felt across a cup run',
        );
    }

    public function test_an_upset_is_possible_but_rare(): void
    {
        foreach (['local', 'elite'] as $level) {
            $rate = $this->upsetRate($level);

            $this->assertGreaterThan(0.001, $rate, "a {$level} ghost should be able to cause an upset");
            $this->assertLessThan(0.10, $rate, "a {$level} ghost causing upsets this often is not an upset");
        }
    }

    public function test_the_better_ghost_causes_more_upsets(): void
    {
        $this->assertGreaterThan(
            $this->upsetRate('local'),
            $this->upsetRate('elite'),
            'a second-tier club should knock out a top-flight side more often than a village side does',
        );
    }

    /**
     * Share of ties the ghost does not lose in normal time — it wins outright,
     * or draws and takes its chance in the shootout.
     */
    private function upsetRate(string $level): float
    {
        [$userXG, $ghostXG] = $this->expectedGoals($level);

        $upsets = 0;
        for ($i = 0; $i < self::RUNS; $i++) {
            [$user, $ghost] = MatchOutcomeModel::sampleScoreline($userXG, $ghostXG);

            if ($ghost > $user) {
                $upsets++;
            } elseif ($ghost === $user) {
                // A level tie goes to penalties, which for a squad-less side
                // MatchSimulator resolves as a coin flip.
                $upsets += 0.5;
            }
        }

        return $upsets / self::RUNS;
    }

    /** @return array{0: float, 1: float} [userXG, ghostXG] */
    private function expectedGoals(string $level): array
    {
        return MatchOutcomeModel::expectedGoals(
            self::TOP_FLIGHT,
            GhostStrength::forReputation($level),
            false,
        );
    }
}
