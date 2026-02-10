<?php

namespace Tests\Unit\Handlers;

use App\Game\Handlers\LeagueHandler;
use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeagueHandlerTest extends TestCase
{
    use RefreshDatabase;

    private LeagueHandler $handler;
    private Game $game;
    private Competition $competition;
    private Team $team1;
    private Team $team2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new LeagueHandler();

        $user = User::factory()->create();
        $this->team1 = Team::factory()->create();
        $this->team2 = Team::factory()->create();

        $this->competition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $this->team1->id,
            'competition_id' => $this->competition->id,
        ]);
    }

    public function test_get_type_returns_league(): void
    {
        $this->assertEquals('league', $this->handler->getType());
    }

    public function test_get_match_batch_returns_all_matches_from_same_round(): void
    {
        $team3 = Team::factory()->create();
        $team4 = Team::factory()->create();

        // Create matches for matchday 1 on different days
        $match1 = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $this->team1->id,
            'away_team_id' => $this->team2->id,
            'scheduled_date' => Carbon::parse('2024-08-16'), // Friday
        ]);

        $match2 = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $team3->id,
            'away_team_id' => $team4->id,
            'scheduled_date' => Carbon::parse('2024-08-17'), // Saturday
        ]);

        // Match from different round (should not be included)
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 2,
            'home_team_id' => $this->team1->id,
            'away_team_id' => $team3->id,
            'scheduled_date' => Carbon::parse('2024-08-23'),
        ]);

        $batch = $this->handler->getMatchBatch($this->game->id, $match1);

        $this->assertCount(2, $batch);
        $this->assertTrue($batch->contains('id', $match1->id));
        $this->assertTrue($batch->contains('id', $match2->id));
    }

    public function test_get_match_batch_excludes_cup_matches(): void
    {
        // Create a cup competition and tie for the cup match
        $cupCompetition = Competition::factory()->knockoutCup()->create(['id' => 'CUP1']);
        $cupTie = CupTie::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $cupCompetition->id,
        ]);

        // Create a league match
        $leagueMatch = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $this->team1->id,
            'away_team_id' => $this->team2->id,
            'cup_tie_id' => null,
        ]);

        // Create a cup match with same round number (should be excluded)
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $cupCompetition->id,
            'round_number' => 1,
            'home_team_id' => $this->team1->id,
            'away_team_id' => $this->team2->id,
            'cup_tie_id' => $cupTie->id,
        ]);

        $batch = $this->handler->getMatchBatch($this->game->id, $leagueMatch);

        $this->assertCount(1, $batch);
        $this->assertEquals($leagueMatch->id, $batch->first()->id);
    }

    public function test_get_match_batch_excludes_played_matches(): void
    {
        // Create an unplayed match
        $unplayedMatch = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $this->team1->id,
            'away_team_id' => $this->team2->id,
            'played' => false,
        ]);

        // Create a played match
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => Team::factory()->create()->id,
            'away_team_id' => Team::factory()->create()->id,
            'played' => true,
        ]);

        $batch = $this->handler->getMatchBatch($this->game->id, $unplayedMatch);

        $this->assertCount(1, $batch);
        $this->assertEquals($unplayedMatch->id, $batch->first()->id);
    }

    public function test_before_matches_does_nothing(): void
    {
        // This should not throw any exceptions
        $this->handler->beforeMatches($this->game, '2024-08-16');

        // Assert nothing changed (this is a no-op for leagues)
        $this->assertTrue(true);
    }

    public function test_after_matches_does_nothing(): void
    {
        // This should not throw any exceptions
        $this->handler->afterMatches($this->game, collect(), collect());

        // Assert nothing changed (standings are updated by projector, not handler)
        $this->assertTrue(true);
    }

    public function test_get_redirect_route_returns_results_page(): void
    {
        $route = $this->handler->getRedirectRoute($this->game, collect(), 5);

        $this->assertStringContainsString('/game/', $route);
        $this->assertStringContainsString('/results/', $route);
        $this->assertStringContainsString('5', $route);
    }
}
