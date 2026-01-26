<?php

namespace Tests\Feature;

use App\Http\Actions\AdvanceMatchday;
use App\Models\Competition;
use App\Models\CupRoundTemplate;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvanceMatchdayTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $playerTeam;
    private Team $opponentTeam;
    private Competition $leagueCompetition;
    private Competition $cupCompetition;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->playerTeam = Team::factory()->create(['name' => 'Player Team']);
        $this->opponentTeam = Team::factory()->create(['name' => 'Opponent Team']);

        $this->leagueCompetition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        $this->cupCompetition = Competition::factory()->knockoutCup()->create([
            'id' => 'ESPCUP',
            'name' => 'Copa del Rey',
        ]);

        // Link teams to competitions
        $this->playerTeam->competitions()->attach($this->leagueCompetition->id, ['season' => '2024']);
        $this->opponentTeam->competitions()->attach($this->leagueCompetition->id, ['season' => '2024']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->playerTeam->id,
            'season' => '2024',
            'current_date' => '2024-08-15',
            'current_matchday' => 0,
        ]);
    }

    public function test_advances_all_league_matches_in_same_matchday(): void
    {
        // Create additional teams
        $team3 = Team::factory()->create(['name' => 'Team 3']);
        $team4 = Team::factory()->create(['name' => 'Team 4']);

        // Create matches for matchday 1 on different days (Friday and Saturday)
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->leagueCompetition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-16'), // Friday
        ]);

        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->leagueCompetition->id,
            'round_number' => 1,
            'home_team_id' => $team3->id,
            'away_team_id' => $team4->id,
            'scheduled_date' => Carbon::parse('2024-08-17'), // Saturday
        ]);

        // Initialize standings
        foreach ([$this->playerTeam, $this->opponentTeam, $team3, $team4] as $team) {
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => $this->leagueCompetition->id,
                'team_id' => $team->id,
                'position' => 0,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
            ]);
        }

        // Advance matchday
        $action = app(AdvanceMatchday::class);
        $response = $action($this->game->id);

        // Both matches should be played (same matchday, different dates)
        $this->assertDatabaseHas('game_matches', [
            'game_id' => $this->game->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'played' => true,
        ]);

        $this->assertDatabaseHas('game_matches', [
            'game_id' => $this->game->id,
            'round_number' => 1,
            'home_team_id' => $team3->id,
            'played' => true,
        ]);

        // All 4 teams should have 1 game played in standings
        $standings = GameStanding::where('game_id', $this->game->id)->get();
        foreach ($standings as $standing) {
            $this->assertEquals(1, $standing->played, "Team {$standing->team_id} should have 1 game played");
        }
    }

    public function test_updates_standings_for_league_matches(): void
    {
        // Create a league match
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->leagueCompetition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-16'),
        ]);

        // Initialize standings
        foreach ([$this->playerTeam, $this->opponentTeam] as $team) {
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => $this->leagueCompetition->id,
                'team_id' => $team->id,
                'position' => 0,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
            ]);
        }

        // Advance matchday
        $action = app(AdvanceMatchday::class);
        $action($this->game->id);

        // Get the match result
        $match = GameMatch::where('game_id', $this->game->id)->first();
        $this->assertTrue($match->played);

        // Verify standings were updated
        $homeStanding = GameStanding::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->first();

        $awayStanding = GameStanding::where('game_id', $this->game->id)
            ->where('team_id', $this->opponentTeam->id)
            ->first();

        $this->assertEquals(1, $homeStanding->played);
        $this->assertEquals(1, $awayStanding->played);

        // Total points should be 3 (winner) or 2 (draw)
        $totalPoints = $homeStanding->points + $awayStanding->points;
        $this->assertTrue(
            $totalPoints === 3 || $totalPoints === 2,
            "Total points should be 3 (win) or 2 (draw), got {$totalPoints}"
        );
    }

    public function test_cup_matches_are_advanced_by_date(): void
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

        // Create a cup tie
        $cupTie = CupTie::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
        ]);

        // Create cup match
        $cupMatch = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-11-01'),
            'cup_tie_id' => $cupTie->id,
        ]);

        // Update the cup tie with match ID
        $cupTie->update(['first_leg_match_id' => $cupMatch->id]);

        // Update game current date to before cup match
        $this->game->update(['current_date' => '2024-10-31']);

        // Advance matchday
        $action = app(AdvanceMatchday::class);
        $action($this->game->id);

        // Cup match should be played
        $this->assertDatabaseHas('game_matches', [
            'id' => $cupMatch->id,
            'played' => true,
        ]);
    }

    public function test_resolves_single_leg_cup_tie(): void
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

        // Create a cup tie
        $cupTie = CupTie::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
        ]);

        // Create cup match
        $cupMatch = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-11-01'),
            'cup_tie_id' => $cupTie->id,
        ]);

        $cupTie->update(['first_leg_match_id' => $cupMatch->id]);

        // Advance matchday
        $action = app(AdvanceMatchday::class);
        $action($this->game->id);

        // Cup tie should be completed with a winner
        $cupTie->refresh();
        $this->assertTrue($cupTie->completed);
        $this->assertNotNull($cupTie->winner_id);
        $this->assertContains($cupTie->winner_id, [
            $this->playerTeam->id,
            $this->opponentTeam->id,
        ]);
    }

    public function test_does_not_update_standings_for_cup_matches(): void
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

        // Create league standings (these should NOT be updated by cup match)
        foreach ([$this->playerTeam, $this->opponentTeam] as $team) {
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => $this->leagueCompetition->id,
                'team_id' => $team->id,
                'position' => 0,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
            ]);
        }

        // Create a cup tie and match
        $cupTie = CupTie::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
        ]);

        $cupMatch = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-11-01'),
            'cup_tie_id' => $cupTie->id,
        ]);

        $cupTie->update(['first_leg_match_id' => $cupMatch->id]);

        // Advance matchday
        $action = app(AdvanceMatchday::class);
        $action($this->game->id);

        // League standings should NOT be updated
        $standings = GameStanding::where('game_id', $this->game->id)->get();
        foreach ($standings as $standing) {
            $this->assertEquals(0, $standing->played, 'League standings should not be affected by cup matches');
        }
    }

    public function test_returns_season_complete_when_no_matches(): void
    {
        // No matches created - should return season complete message
        $action = app(AdvanceMatchday::class);
        $response = $action($this->game->id);

        $this->assertTrue($response->isRedirect());
        // Redirects to /game/{id}
        $this->assertStringContainsString('/game/', $response->getTargetUrl());
        $this->assertStringContainsString($this->game->id, $response->getTargetUrl());
    }
}
