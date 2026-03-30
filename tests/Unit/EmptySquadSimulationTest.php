<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\Team;
use App\Modules\Match\Services\MatchSimulator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesLineups;

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

    public function test_empty_away_squad_always_scores_zero(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();
        $homePlayers = $this->createLineup($game, $homeTeam, 11, 75);
        $awayPlayers = collect(); // Empty squad

        // Run 20 simulations to verify the empty team never scores
        for ($i = 0; $i < 20; $i++) {
            $output = $this->simulator->simulate(
                $homeTeam, $awayTeam,
                $homePlayers, $awayPlayers,
            );

            $this->assertEquals(0, $output->result->awayScore, "Empty squad should never score (iteration {$i})");
            $this->assertGreaterThanOrEqual(0, $output->result->homeScore);
        }
    }

    public function test_empty_home_squad_always_scores_zero(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();
        $homePlayers = collect(); // Empty squad
        $awayPlayers = $this->createLineup($game, $awayTeam, 11, 75);

        for ($i = 0; $i < 20; $i++) {
            $output = $this->simulator->simulate(
                $homeTeam, $awayTeam,
                $homePlayers, $awayPlayers,
            );

            $this->assertEquals(0, $output->result->homeScore, "Empty squad should never score (iteration {$i})");
            $this->assertGreaterThanOrEqual(0, $output->result->awayScore);
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
                // Verify goal events exist for every home goal
                $goalEvents = $output->result->events->filter(fn ($e) => $e->type === 'goal');
                $this->assertEquals($output->result->homeScore, $goalEvents->count(),
                    'Each home goal should have a corresponding event');
                $foundGoalEvents = true;
                break;
            }
        }

        $this->assertTrue($foundGoalEvents, 'Home team should score in at least one of 50 simulations');
    }

    public function test_empty_squad_extra_time_scores_zero(): void
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

            $this->assertEquals(0, $result->awayScore, "Empty squad should never score in ET (iteration {$i})");
        }
    }

    public function test_empty_squad_remainder_scores_zero(): void
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

            $this->assertEquals(0, $output->result->awayScore, "Empty squad should never score in remainder (iteration {$i})");
        }
    }
}
