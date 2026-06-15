<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Team;
use App\Modules\Match\Services\MatchSimulator;
use App\Modules\Match\Support\MatchOutcomeModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesLineups;

/**
 * Guards against the exploding scorelines (13-0, 21-0, 0-15) that the
 * distribution-derived strength floor introduced (#1281).
 *
 * The floor is calibrated on STATIC top-11 overall_score, but the live
 * simulator applies it to MATCH-TIME strength (eroded by form, energy and
 * out-of-position penalties), which can fall far below the static band the
 * floor was derived from. As a side nears the floor, applyFloor() collapses its
 * rescaled strength toward the 0.02 clamp while the opponent stays high, so the
 * home/away ratio explodes and `ratio^skill_dominance` produces enormous xG.
 *
 * The fix bounds the ratio before exponentiation (max_strength_ratio) and
 * hard-caps the match total (max_goals_cap) with event-consistent trimming.
 * Pre-fix, the multi-period scenario below blows past max_goals_cap; post-fix it
 * stays within it, and the headline score always matches the scoring events.
 */
class MatchStrengthRatioBlowoutGuardTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLineups;

    private MatchSimulator $simulator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simulator = new MatchSimulator;
    }

    public function test_expected_goals_ratio_is_clamped_before_exponentiation(): void
    {
        config([
            'match_simulation.max_strength_ratio' => 2.2,
            'match_simulation.skill_dominance' => 2.4,
            'match_simulation.base_goals' => 1.4,
        ]);

        // Raw ratio = 0.80 / 0.05 = 16, which unclamped would be 16^2.4 ≈ 775×
        // base goals (~1085 xG). Clamped to 2.2 it must match a 2.2× ratio.
        [$homeXG, $awayXG] = MatchOutcomeModel::expectedGoals(0.80, 0.05, neutralVenue: true);

        $this->assertEqualsWithDelta(pow(2.2, 2.4) * 1.4, $homeXG, 1e-6);
        $this->assertEqualsWithDelta(pow(1.0 / 2.2, 2.4) * 1.4, $awayXG, 1e-6);
        $this->assertLessThan(10.0, $homeXG);
    }

    public function test_clamp_protects_a_weak_home_side_symmetrically(): void
    {
        config([
            'match_simulation.max_strength_ratio' => 2.2,
            'match_simulation.skill_dominance' => 2.4,
            'match_simulation.base_goals' => 1.4,
        ]);

        // Home is the weak side now: ratio 1/16 clamps up to 1/2.2.
        [$homeXG, $awayXG] = MatchOutcomeModel::expectedGoals(0.05, 0.80, neutralVenue: true);

        $this->assertEqualsWithDelta(pow(2.2, 2.4) * 1.4, $awayXG, 1e-6);
        $this->assertEqualsWithDelta(pow(1.0 / 2.2, 2.4) * 1.4, $homeXG, 1e-6);
    }

    public function test_max_strength_ratio_zero_disables_the_clamp(): void
    {
        config([
            'match_simulation.max_strength_ratio' => 0,
            'match_simulation.skill_dominance' => 2.4,
            'match_simulation.base_goals' => 1.4,
        ]);

        // With the clamp disabled the raw 16× ratio comes through, restoring the
        // pre-fix (unbounded) behaviour as a rollback escape hatch.
        [$homeXG] = MatchOutcomeModel::expectedGoals(0.80, 0.05, neutralVenue: true);

        $this->assertEqualsWithDelta(pow(16.0, 2.4) * 1.4, $homeXG, 1e-3);
        $this->assertGreaterThan(100.0, $homeXG);
    }

    public function test_full_match_total_never_exceeds_cap_for_extreme_mismatch(): void
    {
        $cap = (int) config('match_simulation.max_goals_cap', 6);
        $this->assertGreaterThan(0, $cap, 'This guard test requires max_goals_cap > 0');

        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        // Strong fresh home vs a much weaker away. A high floor stands in for the
        // match-time strength erosion (form/energy/out-of-position) that pushes a
        // side toward the floor in real matches — it is what explodes the ratio.
        $homePlayers = $this->createLineup($game, $homeTeam, 11, 88);
        $awayPlayers = $this->createLineup($game, $awayTeam, 11, 70);
        $homeBench = $this->createBenchPlayers($game, $homeTeam, 7, 85);
        $awayBench = $this->createBenchPlayers($game, $awayTeam, 7, 66);

        // Benches enable AI substitution windows → multiple simulation periods.
        // Pre-fix each period caps at max_goals_cap but the TOTAL stacks past it.
        $this->simulator->setStrengthFloor(60.0);

        for ($i = 0; $i < 30; $i++) {
            $output = $this->simulator->simulate(
                $homeTeam, $awayTeam,
                $homePlayers, $awayPlayers,
                game: $game,
                homeBenchPlayers: $homeBench,
                awayBenchPlayers: $awayBench,
            );

            $result = $output->result;

            $this->assertLessThanOrEqual($cap, $result->homeScore, "home total exceeded cap (iter {$i})");
            $this->assertLessThanOrEqual($cap, $result->awayScore, "away total exceeded cap (iter {$i})");

            // Score must stay consistent with the events after trimming: a team's
            // score = its own goals + the opponent's own goals.
            $homeScoringEvents = $result->events
                ->filter(fn ($e) => ($e->type === 'goal' && $e->teamId === $homeTeam->id)
                    || ($e->type === 'own_goal' && $e->teamId === $awayTeam->id))
                ->count();
            $awayScoringEvents = $result->events
                ->filter(fn ($e) => ($e->type === 'goal' && $e->teamId === $awayTeam->id)
                    || ($e->type === 'own_goal' && $e->teamId === $homeTeam->id))
                ->count();

            $this->assertSame($result->homeScore, $homeScoringEvents, "home score/event mismatch (iter {$i})");
            $this->assertSame($result->awayScore, $awayScoringEvents, "away score/event mismatch (iter {$i})");
        }
    }
}
