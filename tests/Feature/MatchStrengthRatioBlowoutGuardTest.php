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
 * distribution-derived strength floor introduced (#1281, #1283).
 *
 * Root cause: the floor is calibrated on STATIC top-11 overall_score, but the
 * full simulator used to apply it to MATCH-TIME strength (overall × form ×
 * energy × out-of-position penalty). A fatigued or out-of-position side would
 * erode below the floor, collapse toward applyFloor()'s 0.02 clamp while the
 * opponent stayed high, and explode the home/away ratio — pinning xG at the
 * ratio-clamp ceiling on essentially every match. A post-hoc goal cap papered
 * over the result, but it was wired into simulate() only, so the live
 * resimulation path (a substitution or "Skip to end" in a played match) still
 * blew up.
 *
 * The fix floors STATIC ability and applies the match-time modifiers as
 * multipliers afterwards — calculateTeamStrength now mirrors AIMatchResolver, so
 * the ratio stays anchored to the calibrated band on every path and the goal cap
 * is gone entirely. The ratio clamp (max_strength_ratio) remains as the single
 * model bound for genuine cross-league mismatches.
 *
 * These tests cover the ratio clamp (MatchOutcomeModel) and the floor-ordering
 * fix via the full simulate path; the live resimulation path shares the same
 * calculateTeamStrength, so it inherits the fix with no path-specific code.
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

    public function test_match_time_fatigue_does_not_explode_an_equal_ability_matchup(): void
    {
        // Two squads of IDENTICAL static ability; the only difference is that the
        // away side is exhausted (low fitness → heavy energy erosion). Flooring
        // STATIC ability keeps their pre-modifier strengths equal, so the most
        // fatigue can do is scale the away side down by the energy modifier — it
        // can no longer drag them through the floor into the 0.02 collapse that
        // used to pin xG at the ratio-clamp ceiling. The floor sits well below
        // both squads, standing in for a normal league band.
        config(['match_simulation.max_strength_ratio' => 2.2]);

        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        $homePlayers = $this->createLineup($game, $homeTeam, 11, 78);
        $awayPlayers = $this->createLineup($game, $awayTeam, 11, 78);
        // Exhaust the away side. Pre-fix this collapsed their match-time strength
        // below the floor and exploded the ratio; post-fix it is just a multiplier
        // on a floored-static baseline that stays equal to the home side's.
        $awayPlayers->each(fn ($p) => $p->fitness = 10);

        $this->simulator->setStrengthFloor(45.0);

        $iterations = 30;
        $totalHomeXG = 0.0;
        for ($i = 0; $i < $iterations; $i++) {
            $result = $this->simulator->simulate(
                $homeTeam, $awayTeam,
                $homePlayers, $awayPlayers,
                game: $game,
            )->result;

            // With nothing trimmed post-hoc, the headline score must always equal
            // the scoring events: a team's score = its goals + opponent own goals.
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

            $totalHomeXG += $result->homeXG;
        }

        // Pre-fix the collapse pinned the ratio at the clamp (2.2), giving
        // homeXG ≈ 2.2^2.4 × 1.4 + home-advantage ≈ 9.5 every match. Post-fix the
        // equal static ability holds the ratio at the honest fatigue gap, so homeXG
        // settles around ~6 — a fresh side battering an exhausted one, not a
        // collapse. The 7.5 threshold sits cleanly between the two regimes (30
        // iterations keep the sample mean stable); if it trips, the floor is being
        // applied to match-time strength again and xG has jumped to the ceiling.
        $meanHomeXG = $totalHomeXG / $iterations;
        $this->assertLessThan(7.5, $meanHomeXG,
            "equal-ability fatigue matchup is exploding home xG (mean {$meanHomeXG}) — strength floor is being applied to match-time strength again");
    }
}
