<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\MatchEvent;
use App\Models\Team;
use App\Modules\Match\DTOs\MatchEventData;
use App\Modules\Match\Services\MatchSimulator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Tests\Traits\CreatesLineups;

/**
 * A ghost — a cup entrant with no squad — used to be forbidden from scoring, so
 * the only upset available was a 0-0 and a shootout, at odds of roughly one in
 * eight thousand. It can score now. Its goals are unattributed: ordinary goal
 * events for its own team, carrying a sentinel scorer, never charged to a real
 * player of the side it beat.
 *
 * What must not change: the scoreline and the events always agree. A goal
 * without an event vanishes when a half-time change re-simulates the match, and
 * takes the extra-time trigger of a cup tie with it.
 */
class EmptySquadSimulationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLineups;

    private MatchSimulator $simulator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simulator = new MatchSimulator;
    }

    public function test_every_goal_a_squadless_side_scores_has_an_event(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();
        $homePlayers = $this->createLineup($game, $homeTeam, 11, 75);

        for ($i = 0; $i < 60; $i++) {
            $output = $this->simulator->simulate($homeTeam, $awayTeam, $homePlayers, collect());

            $this->assertUnattributedGoals($output->result->events, $awayTeam, $output->result->awayScore, $i);
            $this->assertNoOwnGoals($output->result->events, $i);
        }
    }

    public function test_a_squadless_side_scores_sometimes_but_rarely(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();
        $homePlayers = $this->createLineup($game, $homeTeam, 11, 75);

        $runs = 400;
        $scored = 0;
        for ($i = 0; $i < $runs; $i++) {
            $output = (new MatchSimulator)->simulate($homeTeam, $awayTeam, $homePlayers, collect());
            $scored += $output->result->awayScore > 0 ? 1 : 0;
        }

        $rate = $scored / $runs;
        $this->assertGreaterThan(0.0, $rate, 'a ghost that can never score has no upset in it');
        $this->assertLessThan(0.35, $rate, 'a ghost scoring this often stops being an upset');
    }

    public function test_two_squadless_sides_still_back_their_goals_with_events(): void
    {
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        for ($i = 0; $i < 20; $i++) {
            $output = $this->simulator->simulate($homeTeam, $awayTeam, collect(), collect());

            // Neither side has a player, so every goal on the board is one
            // nobody is credited with — on both sides.
            $this->assertUnattributedGoals($output->result->events, $homeTeam, $output->result->homeScore, $i);
            $this->assertUnattributedGoals($output->result->events, $awayTeam, $output->result->awayScore, $i);
            $this->assertNoOwnGoals($output->result->events, $i);
        }
    }

    public function test_empty_squad_match_generates_events_for_scoring_team(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();
        $homePlayers = $this->createLineup($game, $homeTeam, 11, 85);
        $awayPlayers = collect(); // Empty squad

        // Run multiple times to find a match where the home team scores
        $foundGoalEvents = false;
        for ($i = 0; $i < 50; $i++) {
            $output = $this->simulator->simulate(
                $homeTeam, $awayTeam,
                $homePlayers, $awayPlayers,
            );

            if ($output->result->homeScore > 0) {
                // The side with players scores through them, with a real scorer
                // on every goal.
                $goalEvents = $output->result->events
                    ->filter(fn (MatchEventData $e) => $e->type === 'goal' && $e->teamId === $homeTeam->id);

                $this->assertCount($output->result->homeScore, $goalEvents,
                    'Each home goal should have a corresponding event');
                $this->assertTrue(
                    $goalEvents->every(fn (MatchEventData $e) => $homePlayers->contains('id', $e->gamePlayerId)),
                    'a side with an XI credits its goals to its own players',
                );
                $foundGoalEvents = true;
                break;
            }
        }

        $this->assertTrue($foundGoalEvents, 'Home team should score in at least one of 50 simulations');
    }

    public function test_extra_time_backs_a_squadless_sides_goals_with_events(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();
        $homePlayers = $this->createLineup($game, $homeTeam, 11, 75);
        $awayPlayers = collect(); // Empty squad

        for ($i = 0; $i < 20; $i++) {
            $result = $this->simulator->simulateExtraTime(
                $homeTeam, $awayTeam,
                $homePlayers, $awayPlayers,
            );

            $this->assertUnattributedGoals($result->events, $awayTeam, $result->awayScore, $i);
            $this->assertNoOwnGoals($result->events, $i);
        }
    }

    public function test_a_resimulated_remainder_backs_a_squadless_sides_goals(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();
        $homePlayers = $this->createLineup($game, $homeTeam, 11, 75);
        $awayPlayers = collect(); // Empty squad

        // Simulate remainder from minute 60 (like after a substitution)
        for ($i = 0; $i < 20; $i++) {
            $output = $this->simulator->simulateRemainder(
                $homeTeam, $awayTeam,
                $homePlayers, $awayPlayers,
                fromMinute: 60,
            );

            $this->assertUnattributedGoals($output->result->events, $awayTeam, $output->result->awayScore, $i);
            $this->assertNoOwnGoals($output->result->events, $i);
        }
    }

    /**
     * Every goal on a squad-less side's scoreline is an unattributed goal event
     * for that side, and there are exactly as many as the scoreline says.
     *
     * @param  Collection<MatchEventData>  $events
     */
    private function assertUnattributedGoals(Collection $events, Team $team, int $score, int $iteration): void
    {
        $goals = $events->filter(
            fn (MatchEventData $e) => $e->type === 'goal' && $e->teamId === $team->id,
        );

        $this->assertCount(
            $score,
            $goals,
            "A squad-less side's goals must each have an event (iteration {$iteration})",
        );

        $this->assertTrue(
            $goals->every(fn (MatchEventData $e) => $e->gamePlayerId === MatchEvent::UNATTRIBUTED_PLAYER_ID),
            "A squad-less side has no scorer to credit (iteration {$iteration})",
        );
    }

    /**
     * The opponent's players are never charged with an own goal to explain a
     * ghost's scoreline — the thing this model exists to stop.
     *
     * @param  Collection<MatchEventData>  $events
     */
    private function assertNoOwnGoals(Collection $events, int $iteration): void
    {
        $this->assertCount(
            0,
            $events->filter(fn (MatchEventData $e) => $e->type === 'own_goal'),
            "No own goal should be invented for a squad-less side's goals (iteration {$iteration})",
        );
    }
}
