<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\ManagerStats;
use App\Models\SeasonArchive;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Match\Listeners\UpdateManagerStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_leaderboard_page_is_accessible(): void
    {
        $response = $this->get('/leaderboard');

        $response->assertOk();
    }

    public function test_leaderboard_shows_qualified_managers(): void
    {
        $user = User::factory()->create([
            'username' => 'topmanager',
            'is_profile_public' => true,
        ]);

        ManagerStats::create([
            'user_id' => $user->id,
            'matches_played' => 20,
            'matches_won' => 15,
            'matches_drawn' => 3,
            'matches_lost' => 2,
            'win_percentage' => 75.00,
            'current_unbeaten_streak' => 5,
            'longest_unbeaten_streak' => 10,
            'seasons_completed' => 1,
        ]);

        $response = $this->get('/leaderboard');

        $response->assertOk();
        $response->assertSee('topmanager');
    }

    public function test_leaderboard_hides_managers_below_minimum_matches(): void
    {
        $user = User::factory()->create([
            'username' => 'newbie',
            'is_profile_public' => true,
        ]);

        ManagerStats::create([
            'user_id' => $user->id,
            'matches_played' => 5,
            'matches_won' => 5,
            'matches_drawn' => 0,
            'matches_lost' => 0,
            'win_percentage' => 100.00,
        ]);

        $response = $this->get('/leaderboard');

        $response->assertOk();
        $response->assertDontSee('newbie');
    }

    public function test_leaderboard_hides_private_profiles(): void
    {
        $user = User::factory()->create([
            'username' => 'hiddenmanager',
            'is_profile_public' => false,
        ]);

        ManagerStats::create([
            'user_id' => $user->id,
            'matches_played' => 20,
            'matches_won' => 15,
            'matches_drawn' => 3,
            'matches_lost' => 2,
            'win_percentage' => 75.00,
        ]);

        $response = $this->get('/leaderboard');

        $response->assertOk();
        $response->assertDontSee('hiddenmanager');
    }

    public function test_leaderboard_filters_by_country(): void
    {
        $spanish = User::factory()->create([
            'username' => 'spanishmanager',
            'is_profile_public' => true,
            'country' => 'ES',
        ]);
        $german = User::factory()->create([
            'username' => 'germanmanager',
            'is_profile_public' => true,
            'country' => 'DE',
        ]);

        foreach ([$spanish, $german] as $user) {
            ManagerStats::create([
                'user_id' => $user->id,
                'matches_played' => 20,
                'matches_won' => 10,
                'matches_drawn' => 5,
                'matches_lost' => 5,
                'win_percentage' => 50.00,
            ]);
        }

        $response = $this->get('/leaderboard?country=ES');

        $response->assertOk();
        $response->assertSee('spanishmanager');
        $response->assertDontSee('germanmanager');
    }

    public function test_listener_records_win(): void
    {
        $team = Team::factory()->create();
        $opponent = Team::factory()->create();
        $user = User::factory()->create();
        $competition = Competition::factory()->league()->create();
        $game = Game::factory()->forTeam($team)->create([
            'user_id' => $user->id,
            'game_mode' => Game::MODE_CAREER,
            'competition_id' => $competition->id,
        ]);

        $match = GameMatch::factory()
            ->forGame($game)
            ->forCompetition($competition)
            ->between($team, $opponent)
            ->played(2, 0)
            ->create();

        $listener = new UpdateManagerStats();
        $listener->handle(new MatchFinalized($match, $game, $competition));

        $stats = ManagerStats::where('user_id', $user->id)->first();
        $this->assertNotNull($stats);
        $this->assertEquals(1, $stats->matches_played);
        $this->assertEquals(1, $stats->matches_won);
        $this->assertEquals(0, $stats->matches_drawn);
        $this->assertEquals(0, $stats->matches_lost);
        $this->assertEquals(100.00, $stats->win_percentage);
        $this->assertEquals(1, $stats->current_unbeaten_streak);
        $this->assertEquals(1, $stats->longest_unbeaten_streak);
    }

    public function test_listener_records_loss_and_resets_streak(): void
    {
        $team = Team::factory()->create();
        $opponent = Team::factory()->create();
        $user = User::factory()->create();
        $competition = Competition::factory()->league()->create();
        $game = Game::factory()->forTeam($team)->create([
            'user_id' => $user->id,
            'game_mode' => Game::MODE_CAREER,
            'competition_id' => $competition->id,
        ]);

        // First: a win to build streak
        $stats = ManagerStats::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'team_id' => $team->id,
            'matches_played' => 3,
            'matches_won' => 3,
            'current_unbeaten_streak' => 3,
            'longest_unbeaten_streak' => 3,
            'win_percentage' => 100.00,
        ]);

        // Now a loss
        $match = GameMatch::factory()
            ->forGame($game)
            ->forCompetition($competition)
            ->between($team, $opponent)
            ->played(0, 2)
            ->create();

        $listener = new UpdateManagerStats();
        $listener->handle(new MatchFinalized($match, $game, $competition));

        $stats->refresh();
        $this->assertEquals(4, $stats->matches_played);
        $this->assertEquals(1, $stats->matches_lost);
        $this->assertEquals(0, $stats->current_unbeaten_streak);
        $this->assertEquals(3, $stats->longest_unbeaten_streak);
    }

    public function test_listener_ignores_tournament_mode(): void
    {
        $team = Team::factory()->create();
        $opponent = Team::factory()->create();
        $user = User::factory()->create();
        $competition = Competition::factory()->league()->create();
        $game = Game::factory()->forTeam($team)->create([
            'user_id' => $user->id,
            'game_mode' => Game::MODE_TOURNAMENT,
            'competition_id' => $competition->id,
        ]);

        $match = GameMatch::factory()
            ->forGame($game)
            ->forCompetition($competition)
            ->between($team, $opponent)
            ->played(3, 0)
            ->create();

        $listener = new UpdateManagerStats();
        $listener->handle(new MatchFinalized($match, $game, $competition));

        $this->assertNull(ManagerStats::where('user_id', $user->id)->first());
    }

    public function test_listener_ignores_matches_not_involving_users_team(): void
    {
        $team = Team::factory()->create();
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $user = User::factory()->create();
        $competition = Competition::factory()->league()->create();
        $game = Game::factory()->forTeam($team)->create([
            'user_id' => $user->id,
            'game_mode' => Game::MODE_CAREER,
            'competition_id' => $competition->id,
        ]);

        // Match between two other teams
        $match = GameMatch::factory()
            ->forGame($game)
            ->forCompetition($competition)
            ->between($teamA, $teamB)
            ->played(1, 1)
            ->create();

        $listener = new UpdateManagerStats();
        $listener->handle(new MatchFinalized($match, $game, $competition));

        $this->assertNull(ManagerStats::where('user_id', $user->id)->first());
    }

    public function test_listener_handles_penalty_win(): void
    {
        $team = Team::factory()->create();
        $opponent = Team::factory()->create();
        $user = User::factory()->create();
        $competition = Competition::factory()->league()->create();
        $game = Game::factory()->forTeam($team)->create([
            'user_id' => $user->id,
            'game_mode' => Game::MODE_CAREER,
            'competition_id' => $competition->id,
        ]);

        $cupTie = CupTie::factory()->create([
            'game_id' => $game->id,
            'competition_id' => $competition->id,
            'home_team_id' => $team->id,
            'away_team_id' => $opponent->id,
        ]);

        $match = GameMatch::factory()
            ->forGame($game)
            ->forCompetition($competition)
            ->between($team, $opponent)
            ->played(1, 1)
            ->cupMatch($cupTie)
            ->withExtraTime(1, 1)
            ->withPenalties(5, 3)
            ->create();

        $listener = new UpdateManagerStats();
        $listener->handle(new MatchFinalized($match, $game, $competition));

        $stats = ManagerStats::where('user_id', $user->id)->first();
        $this->assertEquals(1, $stats->matches_won);
        $this->assertEquals(0, $stats->matches_lost);
    }

    public function test_listener_handles_extra_time_loss(): void
    {
        $team = Team::factory()->create();
        $opponent = Team::factory()->create();
        $user = User::factory()->create();
        $competition = Competition::factory()->league()->create();
        $game = Game::factory()->forTeam($team)->create([
            'user_id' => $user->id,
            'game_mode' => Game::MODE_CAREER,
            'competition_id' => $competition->id,
        ]);

        $match = GameMatch::factory()
            ->forGame($game)
            ->forCompetition($competition)
            ->between($team, $opponent)
            ->played(1, 1)
            ->withExtraTime(1, 2)
            ->create();

        $listener = new UpdateManagerStats();
        $listener->handle(new MatchFinalized($match, $game, $competition));

        $stats = ManagerStats::where('user_id', $user->id)->first();
        $this->assertEquals(0, $stats->matches_won);
        $this->assertEquals(1, $stats->matches_lost);
    }

    public function test_backfill_includes_archived_match_results(): void
    {
        $team = Team::factory()->create();
        $opponent = Team::factory()->create();
        $user = User::factory()->create();
        $competition = Competition::factory()->league()->create();
        $game = Game::factory()->forTeam($team)->create([
            'user_id' => $user->id,
            'game_mode' => Game::MODE_CAREER,
            'competition_id' => $competition->id,
        ]);

        // Create an archive with match results (no GameMatch records exist)
        SeasonArchive::create([
            'game_id' => $game->id,
            'season' => '2025',
            'final_standings' => [],
            'player_season_stats' => [],
            'season_awards' => [],
            'match_results' => [
                ['home_team_id' => $team->id, 'away_team_id' => $opponent->id, 'home_score' => 3, 'away_score' => 1, 'competition_id' => $competition->id, 'round_number' => 1, 'date' => '2024-08-15'],
                ['home_team_id' => $opponent->id, 'away_team_id' => $team->id, 'home_score' => 0, 'away_score' => 0, 'competition_id' => $competition->id, 'round_number' => 2, 'date' => '2024-08-22'],
                ['home_team_id' => $team->id, 'away_team_id' => $opponent->id, 'home_score' => 0, 'away_score' => 2, 'competition_id' => $competition->id, 'round_number' => 3, 'date' => '2024-08-29'],
            ],
        ]);

        $this->artisan('app:backfill-manager-stats')->assertSuccessful();

        $stats = ManagerStats::where('game_id', $game->id)->first();
        $this->assertNotNull($stats);
        $this->assertEquals(3, $stats->matches_played);
        $this->assertEquals(1, $stats->matches_won);
        $this->assertEquals(1, $stats->matches_drawn);
        $this->assertEquals(1, $stats->matches_lost);
        $this->assertEquals(1, $stats->seasons_completed);
    }

    public function test_backfill_combines_archived_and_current_matches(): void
    {
        $team = Team::factory()->create();
        $opponent = Team::factory()->create();
        $user = User::factory()->create();
        $competition = Competition::factory()->league()->create();
        $game = Game::factory()->forTeam($team)->create([
            'user_id' => $user->id,
            'game_mode' => Game::MODE_CAREER,
            'competition_id' => $competition->id,
            'season' => '2026',
        ]);

        // Archived season: 2 wins, 1 loss (streak broken)
        SeasonArchive::create([
            'game_id' => $game->id,
            'season' => '2025',
            'final_standings' => [],
            'player_season_stats' => [],
            'season_awards' => [],
            'match_results' => [
                ['home_team_id' => $team->id, 'away_team_id' => $opponent->id, 'home_score' => 2, 'away_score' => 0, 'competition_id' => $competition->id, 'round_number' => 1, 'date' => '2024-08-15'],
                ['home_team_id' => $opponent->id, 'away_team_id' => $team->id, 'home_score' => 3, 'away_score' => 0, 'competition_id' => $competition->id, 'round_number' => 2, 'date' => '2024-08-22'],
                ['home_team_id' => $team->id, 'away_team_id' => $opponent->id, 'home_score' => 1, 'away_score' => 0, 'competition_id' => $competition->id, 'round_number' => 3, 'date' => '2024-08-29'],
            ],
        ]);

        // Current season: 2 wins (streak continues from last archived win)
        GameMatch::factory()
            ->forGame($game)
            ->forCompetition($competition)
            ->between($team, $opponent)
            ->played(1, 0)
            ->scheduledOn('2025-08-15')
            ->inRound(1)
            ->create();

        GameMatch::factory()
            ->forGame($game)
            ->forCompetition($competition)
            ->between($opponent, $team)
            ->played(0, 2)
            ->scheduledOn('2025-08-22')
            ->inRound(2)
            ->create();

        $this->artisan('app:backfill-manager-stats')->assertSuccessful();

        $stats = ManagerStats::where('game_id', $game->id)->first();
        $this->assertNotNull($stats);
        $this->assertEquals(5, $stats->matches_played);
        $this->assertEquals(4, $stats->matches_won);
        $this->assertEquals(0, $stats->matches_drawn);
        $this->assertEquals(1, $stats->matches_lost);
        // Streak: W, L(reset), W, W(current), W(current) = current 3, longest 3
        $this->assertEquals(3, $stats->current_unbeaten_streak);
        $this->assertEquals(3, $stats->longest_unbeaten_streak);
        $this->assertEquals(1, $stats->seasons_completed);
    }

    public function test_backfill_handles_archived_penalties(): void
    {
        $team = Team::factory()->create();
        $opponent = Team::factory()->create();
        $user = User::factory()->create();
        $competition = Competition::factory()->league()->create();
        $game = Game::factory()->forTeam($team)->create([
            'user_id' => $user->id,
            'game_mode' => Game::MODE_CAREER,
            'competition_id' => $competition->id,
        ]);

        // Archive with enriched ET/penalty data
        SeasonArchive::create([
            'game_id' => $game->id,
            'season' => '2025',
            'final_standings' => [],
            'player_season_stats' => [],
            'season_awards' => [],
            'match_results' => [
                [
                    'home_team_id' => $team->id,
                    'away_team_id' => $opponent->id,
                    'home_score' => 1,
                    'away_score' => 1,
                    'is_extra_time' => true,
                    'home_score_et' => 1,
                    'away_score_et' => 1,
                    'home_score_penalties' => 5,
                    'away_score_penalties' => 3,
                    'competition_id' => $competition->id,
                    'round_number' => 1,
                    'date' => '2024-08-15',
                ],
                [
                    'home_team_id' => $opponent->id,
                    'away_team_id' => $team->id,
                    'home_score' => 2,
                    'away_score' => 2,
                    'is_extra_time' => true,
                    'home_score_et' => 3,
                    'away_score_et' => 2,
                    'home_score_penalties' => null,
                    'away_score_penalties' => null,
                    'competition_id' => $competition->id,
                    'round_number' => 2,
                    'date' => '2024-08-22',
                ],
            ],
        ]);

        $this->artisan('app:backfill-manager-stats')->assertSuccessful();

        $stats = ManagerStats::where('game_id', $game->id)->first();
        $this->assertNotNull($stats);
        $this->assertEquals(2, $stats->matches_played);
        // First match: penalty win. Second match: ET loss.
        $this->assertEquals(1, $stats->matches_won);
        $this->assertEquals(1, $stats->matches_lost);
    }

    public function test_manager_stats_record_result_updates_correctly(): void
    {
        $user = User::factory()->create();
        $stats = ManagerStats::create(['user_id' => $user->id]);

        $stats->recordResult('win');
        $stats->recordResult('win');
        $stats->recordResult('draw');
        $stats->recordResult('loss');
        $stats->recordResult('win');

        $stats->refresh();

        $this->assertEquals(5, $stats->matches_played);
        $this->assertEquals(3, $stats->matches_won);
        $this->assertEquals(1, $stats->matches_drawn);
        $this->assertEquals(1, $stats->matches_lost);
        $this->assertEquals(60.00, $stats->win_percentage);
        $this->assertEquals(1, $stats->current_unbeaten_streak);
        $this->assertEquals(3, $stats->longest_unbeaten_streak);
    }
}
