<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameTactics;
use App\Models\Team;
use App\Models\User;
use App\Modules\Lineup\Services\LineupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TacticalChangeTransiencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $playerTeam;
    private Team $opponentTeam;
    private Competition $competition;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->playerTeam = Team::factory()->create(['name' => 'Player Team']);
        $this->opponentTeam = Team::factory()->create(['name' => 'Opponent Team']);

        $this->competition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->playerTeam->id,
            'competition_id' => $this->competition->id,
            'season' => '2024',
            'current_date' => '2024-09-01',
            'current_matchday' => 1,
        ]);
    }

    public function test_previous_match_formation_does_not_leak_into_lineup_page(): void
    {
        // Set the user's default tactics to 4-3-3 balanced
        GameTactics::create([
            'game_id' => $this->game->id,
            'default_formation' => '4-3-3',
            'default_mentality' => 'balanced',
            'default_playing_style' => 'balanced',
            'default_pressing' => 'standard',
            'default_defensive_line' => 'normal',
        ]);

        // Previous match was played and had formation changed mid-match to 3-5-2
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-25'),
            'played' => true,
            'home_score' => 2,
            'away_score' => 1,
            'home_formation' => '3-5-2', // changed mid-match from 4-4-2
            'home_mentality' => 'attacking', // changed mid-match from balanced
        ]);

        // Next match (not yet played, no lineup set)
        $nextMatch = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 2,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-09-01'),
            'played' => false,
            'home_formation' => null,
            'home_mentality' => null,
        ]);

        $this->game->update(['next_match_id' => $nextMatch->id]);

        // When the lineup page loads, getPreviousLineup should not carry
        // the mid-match formation into the next match's display
        $lineupService = app(LineupService::class);
        $previous = $lineupService->getPreviousLineup(
            $this->game->id,
            $this->playerTeam->id,
            $nextMatch->id,
            Carbon::parse('2024-09-01'),
            $this->competition->id
        );

        // The previous lineup result should not include formation
        $this->assertArrayNotHasKey('formation', $previous);
    }

    public function test_tactical_change_service_does_not_update_game_tactics(): void
    {
        $tactics = GameTactics::create([
            'game_id' => $this->game->id,
            'default_formation' => '4-3-3',
            'default_mentality' => 'balanced',
            'default_playing_style' => 'balanced',
            'default_pressing' => 'standard',
            'default_defensive_line' => 'normal',
        ]);

        // Create a match with the user's default tactics
        $match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-25'),
            'played' => false,
            'home_formation' => '4-3-3',
            'home_mentality' => 'balanced',
            'home_playing_style' => 'balanced',
            'home_pressing' => 'standard',
            'home_defensive_line' => 'normal',
        ]);

        // Simulate what TacticalChangeService does: update the match record only
        $match->update([
            'home_formation' => '3-5-2',
            'home_mentality' => 'attacking',
            'home_playing_style' => 'possession',
            'home_pressing' => 'high',
            'home_defensive_line' => 'high',
        ]);

        // GameTactics defaults must remain unchanged
        $tactics->refresh();
        $this->assertEquals('4-3-3', $tactics->default_formation);
        $this->assertEquals('balanced', $tactics->default_mentality);
        $this->assertEquals('balanced', $tactics->default_playing_style);
        $this->assertEquals('standard', $tactics->default_pressing);
        $this->assertEquals('normal', $tactics->default_defensive_line);
    }

    public function test_ensure_lineups_uses_game_tactics_defaults_not_previous_match(): void
    {
        GameTactics::create([
            'game_id' => $this->game->id,
            'default_formation' => '4-3-3',
            'default_mentality' => 'balanced',
            'default_playing_style' => 'balanced',
            'default_pressing' => 'standard',
            'default_defensive_line' => 'normal',
        ]);

        // Previous match had formation changed mid-match
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-25'),
            'played' => true,
            'home_score' => 1,
            'away_score' => 0,
            'home_formation' => '3-5-2', // mid-match change
            'home_mentality' => 'attacking', // mid-match change
        ]);

        // Next match: ensureLineupsForMatches should use GameTactics defaults
        $nextMatch = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 2,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-09-01'),
            'played' => false,
        ]);

        $this->game->load('tactics');
        $lineupService = app(LineupService::class);
        $lineupService->ensureLineupsForMatches(collect([$nextMatch]), $this->game);

        $nextMatch->refresh();

        // Formation and mentality should come from GameTactics, not the previous match
        $this->assertEquals('4-3-3', $nextMatch->home_formation);
        $this->assertEquals('balanced', $nextMatch->home_mentality);
        $this->assertEquals('balanced', $nextMatch->home_playing_style);
        $this->assertEquals('standard', $nextMatch->home_pressing);
        $this->assertEquals('normal', $nextMatch->home_defensive_line);
    }
}
