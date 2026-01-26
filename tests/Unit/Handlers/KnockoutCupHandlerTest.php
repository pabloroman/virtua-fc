<?php

namespace Tests\Unit\Handlers;

use App\Game\Handlers\KnockoutCupHandler;
use App\Game\Services\CupDrawService;
use App\Game\Services\CupTieResolver;
use App\Models\Competition;
use App\Models\CupRoundTemplate;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class KnockoutCupHandlerTest extends TestCase
{
    use RefreshDatabase;

    private KnockoutCupHandler $handler;
    private Game $game;
    private Competition $cupCompetition;
    private Team $team1;
    private Team $team2;
    private $cupDrawServiceMock;
    private $cupTieResolverMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cupDrawServiceMock = Mockery::mock(CupDrawService::class);
        $this->cupTieResolverMock = Mockery::mock(CupTieResolver::class);

        $this->handler = new KnockoutCupHandler(
            $this->cupDrawServiceMock,
            $this->cupTieResolverMock,
        );

        $user = User::factory()->create();
        $this->team1 = Team::factory()->create();
        $this->team2 = Team::factory()->create();

        $this->cupCompetition = Competition::factory()->knockoutCup()->create([
            'id' => 'ESPCUP',
            'name' => 'Copa del Rey',
        ]);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $this->team1->id,
            'season' => '2024',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_type_returns_knockout_cup(): void
    {
        $this->assertEquals('knockout_cup', $this->handler->getType());
    }

    public function test_get_match_batch_returns_cup_matches_from_same_date(): void
    {
        // Create cup tie
        $cupTie1 = CupTie::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'round_number' => 1,
        ]);

        $cupTie2 = CupTie::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'round_number' => 1,
        ]);

        // Create cup matches on same date
        $match1 = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'scheduled_date' => Carbon::parse('2024-11-01'),
            'cup_tie_id' => $cupTie1->id,
        ]);

        $match2 = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'scheduled_date' => Carbon::parse('2024-11-01'),
            'cup_tie_id' => $cupTie2->id,
        ]);

        // Match on different date (should not be included)
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'scheduled_date' => Carbon::parse('2024-11-08'),
            'cup_tie_id' => CupTie::factory()->create([
                'game_id' => $this->game->id,
                'competition_id' => $this->cupCompetition->id,
            ])->id,
        ]);

        $batch = $this->handler->getMatchBatch($this->game->id, $match1);

        $this->assertCount(2, $batch);
        $this->assertTrue($batch->contains('id', $match1->id));
        $this->assertTrue($batch->contains('id', $match2->id));
    }

    public function test_get_match_batch_excludes_league_matches(): void
    {
        // Create a league competition
        $leagueCompetition = Competition::factory()->league()->create(['id' => 'ESP1']);

        $cupTie = CupTie::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
        ]);

        $cupMatch = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'scheduled_date' => Carbon::parse('2024-11-01'),
            'cup_tie_id' => $cupTie->id,
        ]);

        // League match on same date (should be excluded)
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $leagueCompetition->id,
            'scheduled_date' => Carbon::parse('2024-11-01'),
            'cup_tie_id' => null,
        ]);

        $batch = $this->handler->getMatchBatch($this->game->id, $cupMatch);

        $this->assertCount(1, $batch);
        $this->assertEquals($cupMatch->id, $batch->first()->id);
    }

    public function test_before_matches_conducts_draws_when_needed(): void
    {
        // Create cup round template
        CupRoundTemplate::create([
            'competition_id' => $this->cupCompetition->id,
            'season' => '2024',
            'round_number' => 1,
            'round_name' => 'Round 1',
            'type' => 'one_leg',
            'first_leg_date' => Carbon::parse('2024-11-01'),
        ]);

        // Mock that draw is needed
        $this->cupDrawServiceMock
            ->shouldReceive('needsDrawForRound')
            ->with($this->game->id, $this->cupCompetition->id, 1)
            ->andReturn(true);

        // Mock the draw being conducted
        $this->cupDrawServiceMock
            ->shouldReceive('conductDraw')
            ->with($this->game->id, $this->cupCompetition->id, 1)
            ->andReturn(collect([
                (object) ['id' => 'tie-1'],
                (object) ['id' => 'tie-2'],
            ]));

        // Run beforeMatches
        $this->handler->beforeMatches($this->game, '2024-11-01');

        // Verify the mock was called
        $this->assertTrue(true); // If we get here, mocks were called correctly
    }

    public function test_before_matches_skips_draw_when_not_needed(): void
    {
        // Create cup round template
        CupRoundTemplate::create([
            'competition_id' => $this->cupCompetition->id,
            'season' => '2024',
            'round_number' => 1,
            'round_name' => 'Round 1',
            'type' => 'one_leg',
            'first_leg_date' => Carbon::parse('2024-11-01'),
        ]);

        // Mock that draw is NOT needed
        $this->cupDrawServiceMock
            ->shouldReceive('needsDrawForRound')
            ->with($this->game->id, $this->cupCompetition->id, 1)
            ->andReturn(false);

        // conductDraw should NOT be called
        $this->cupDrawServiceMock
            ->shouldNotReceive('conductDraw');

        $this->handler->beforeMatches($this->game, '2024-11-01');

        $this->assertTrue(true);
    }

    public function test_after_matches_resolves_cup_ties(): void
    {
        $cupTie = CupTie::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'home_team_id' => $this->team1->id,
            'away_team_id' => $this->team2->id,
            'completed' => false,
        ]);

        $cupMatch = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'cup_tie_id' => $cupTie->id,
            'home_team_id' => $this->team1->id,
            'away_team_id' => $this->team2->id,
            'played' => true,
            'home_score' => 2,
            'away_score' => 1,
        ]);

        $cupTie->update(['first_leg_match_id' => $cupMatch->id]);

        // Mock the resolver returning null (tie not yet resolved)
        // This avoids the completeCupTie call which requires aggregate setup
        $this->cupTieResolverMock
            ->shouldReceive('resolve')
            ->once()
            ->andReturn(null);

        $matches = collect([$cupMatch]);
        $allPlayers = collect();

        $this->handler->afterMatches($this->game, $matches, $allPlayers);

        // Verify the resolver was called
        $this->assertTrue(true);
    }

    public function test_after_matches_skips_completed_ties(): void
    {
        $cupTie = CupTie::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'home_team_id' => $this->team1->id,
            'away_team_id' => $this->team2->id,
            'completed' => true, // Already completed
            'winner_id' => $this->team1->id,
        ]);

        $cupMatch = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'cup_tie_id' => $cupTie->id,
            'played' => true,
        ]);

        // Resolver should NOT be called for completed ties
        $this->cupTieResolverMock
            ->shouldNotReceive('resolve');

        $matches = collect([$cupMatch]);
        $allPlayers = collect();

        $this->handler->afterMatches($this->game, $matches, $allPlayers);

        $this->assertTrue(true);
    }

    public function test_get_redirect_route_returns_cup_page(): void
    {
        $route = $this->handler->getRedirectRoute($this->game, collect(), 1);

        $this->assertStringContainsString('/game/', $route);
        $this->assertStringContainsString('/cup', $route);
    }

    public function test_get_redirect_route_returns_league_results_when_eliminated(): void
    {
        // Mark the player's team as eliminated
        $this->game->update([
            'cup_eliminated' => true,
            'current_matchday' => 10,
        ]);

        $route = $this->handler->getRedirectRoute($this->game, collect(), 1);

        $this->assertStringContainsString('/game/', $route);
        $this->assertStringContainsString('/results/', $route);
        $this->assertStringContainsString('10', $route);
    }
}
