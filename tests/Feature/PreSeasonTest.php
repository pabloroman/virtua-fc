<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreSeasonTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $playerTeam;
    private Team $opponentTeam;
    private Competition $leagueCompetition;
    private Competition $preSeasonCompetition;
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

        $this->preSeasonCompetition = Competition::find('PRESEASON');

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->playerTeam->id,
            'competition_id' => $this->leagueCompetition->id,
            'season' => '2025',
            'current_date' => '2025-07-01',
            'pre_season' => true,
            'setup_completed_at' => now(),
            'needs_new_season_setup' => false,
            'needs_welcome' => false,
        ]);
    }

    public function test_game_reports_pre_season_status(): void
    {
        $this->assertTrue($this->game->isInPreSeason());

        $this->game->endPreSeason();
        $this->game->refresh();

        $this->assertFalse($this->game->isInPreSeason());
    }

    public function test_skip_pre_season_deletes_unplayed_friendlies(): void
    {
        // Create pre-season matches
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'PRESEASON',
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2025-07-12'),
            'played' => false,
        ]);

        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'PRESEASON',
            'home_team_id' => $this->opponentTeam->id,
            'away_team_id' => $this->playerTeam->id,
            'scheduled_date' => Carbon::parse('2025-07-22'),
            'played' => false,
        ]);

        // Create a competitive match
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP1',
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2025-08-17'),
            'played' => false,
            'round_number' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('game.skip-pre-season', $this->game->id));

        $response->assertRedirect(route('show-game', $this->game->id));

        // Friendlies should be deleted
        $this->assertDatabaseMissing('game_matches', [
            'game_id' => $this->game->id,
            'competition_id' => 'PRESEASON',
        ]);

        // Competitive match should still exist
        $this->assertDatabaseHas('game_matches', [
            'game_id' => $this->game->id,
            'competition_id' => 'ESP1',
        ]);

        // Game should no longer be in pre-season and date advanced
        $this->game->refresh();
        $this->assertFalse($this->game->isInPreSeason());
        $this->assertEquals('2025-08-17', $this->game->current_date->toDateString());
    }

    public function test_skip_pre_season_redirects_if_not_in_pre_season(): void
    {
        $this->game->update(['pre_season' => false]);

        $response = $this->actingAs($this->user)
            ->post(route('game.skip-pre-season', $this->game->id));

        $response->assertRedirect(route('show-game', $this->game->id));
    }

    public function test_show_game_passes_pre_season_data(): void
    {
        // Create a competitive match for season start date
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP1',
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2025-08-17'),
            'played' => false,
            'round_number' => 1,
        ]);

        // Create a pre-season match as next match
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'PRESEASON',
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2025-07-12'),
            'played' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('show-game', $this->game->id));

        $response->assertOk();
        $response->assertViewHas('isPreSeason', true);
        $response->assertViewHas('seasonStartDate');
    }
}
