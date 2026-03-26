<?php

namespace Tests\Feature;

use App\Models\ManagerStats;
use App\Models\Team;
use App\Models\User;
use App\Modules\Manager\Services\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_leaderboard_index_is_accessible(): void
    {
        $response = $this->get('/leaderboard/teams');

        $response->assertOk();
    }

    public function test_team_leaderboard_index_shows_teams_with_qualifying_managers(): void
    {
        $team = Team::factory()->create(['name' => 'Real Madrid', 'slug' => 'real-madrid']);
        $user = User::factory()->create(['is_profile_public' => true]);

        ManagerStats::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'matches_played' => 20,
            'matches_won' => 15,
            'matches_drawn' => 3,
            'matches_lost' => 2,
            'win_percentage' => 75.00,
            'seasons_completed' => 1,
        ]);

        $response = $this->get('/leaderboard/teams');

        $response->assertOk();
        $response->assertSee('Real Madrid');
    }

    public function test_team_leaderboard_index_hides_teams_without_qualifying_managers(): void
    {
        $team = Team::factory()->create(['name' => 'Small Club', 'slug' => 'small-club']);
        $user = User::factory()->create(['is_profile_public' => true]);

        ManagerStats::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'matches_played' => 5,
            'matches_won' => 3,
            'matches_drawn' => 1,
            'matches_lost' => 1,
            'win_percentage' => 60.00,
        ]);

        $response = $this->get('/leaderboard/teams');

        $response->assertOk();
        $response->assertDontSee('Small Club');
    }

    public function test_individual_team_leaderboard_is_accessible(): void
    {
        $team = Team::factory()->create(['name' => 'FC Barcelona', 'slug' => 'fc-barcelona']);

        $response = $this->get('/leaderboard/team/fc-barcelona');

        $response->assertOk();
        $response->assertSee('FC Barcelona');
    }

    public function test_individual_team_leaderboard_shows_qualifying_managers(): void
    {
        $team = Team::factory()->create(['name' => 'Atletico Madrid', 'slug' => 'atletico-madrid']);
        $user = User::factory()->create([
            'username' => 'cholo',
            'is_profile_public' => true,
        ]);

        ManagerStats::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'matches_played' => 30,
            'matches_won' => 20,
            'matches_drawn' => 5,
            'matches_lost' => 5,
            'win_percentage' => 66.67,
            'longest_unbeaten_streak' => 12,
            'seasons_completed' => 2,
        ]);

        $response = $this->get('/leaderboard/team/atletico-madrid');

        $response->assertOk();
        $response->assertSee('cholo');
    }

    public function test_individual_team_leaderboard_only_shows_managers_for_that_team(): void
    {
        $teamA = Team::factory()->create(['name' => 'Team Alpha', 'slug' => 'team-alpha']);
        $teamB = Team::factory()->create(['name' => 'Team Beta', 'slug' => 'team-beta']);

        $userA = User::factory()->create(['username' => 'manager_a', 'is_profile_public' => true]);
        $userB = User::factory()->create(['username' => 'manager_b', 'is_profile_public' => true]);

        ManagerStats::create([
            'user_id' => $userA->id,
            'team_id' => $teamA->id,
            'matches_played' => 20,
            'matches_won' => 15,
            'matches_drawn' => 3,
            'matches_lost' => 2,
            'win_percentage' => 75.00,
            'seasons_completed' => 1,
        ]);

        ManagerStats::create([
            'user_id' => $userB->id,
            'team_id' => $teamB->id,
            'matches_played' => 20,
            'matches_won' => 10,
            'matches_drawn' => 5,
            'matches_lost' => 5,
            'win_percentage' => 50.00,
            'seasons_completed' => 1,
        ]);

        $response = $this->get('/leaderboard/team/team-alpha');

        $response->assertOk();
        $response->assertSee('manager_a');
        $response->assertDontSee('manager_b');
    }

    public function test_individual_team_leaderboard_supports_sorting(): void
    {
        $team = Team::factory()->create(['slug' => 'test-team']);

        $user1 = User::factory()->create(['username' => 'high_win', 'is_profile_public' => true]);
        $user2 = User::factory()->create(['username' => 'many_matches', 'is_profile_public' => true]);

        ManagerStats::create([
            'user_id' => $user1->id,
            'team_id' => $team->id,
            'matches_played' => 15,
            'matches_won' => 14,
            'matches_drawn' => 1,
            'matches_lost' => 0,
            'win_percentage' => 93.33,
            'seasons_completed' => 1,
        ]);

        ManagerStats::create([
            'user_id' => $user2->id,
            'team_id' => $team->id,
            'matches_played' => 50,
            'matches_won' => 25,
            'matches_drawn' => 10,
            'matches_lost' => 15,
            'win_percentage' => 50.00,
            'seasons_completed' => 3,
        ]);

        $response = $this->get('/leaderboard/team/' . $team->slug . '?sort=matches_played');

        $response->assertOk();
    }

    public function test_individual_team_leaderboard_returns_404_for_invalid_slug(): void
    {
        $response = $this->get('/leaderboard/team/nonexistent-team');

        $response->assertNotFound();
    }

    public function test_main_leaderboard_has_link_to_teams_index(): void
    {
        $response = $this->get('/leaderboard');

        $response->assertOk();
        $response->assertSee(route('leaderboard.teams'));
    }

    public function test_team_leaderboard_url_is_shareable(): void
    {
        $team = Team::factory()->create(['name' => 'Real Madrid', 'slug' => 'real-madrid']);

        $url = route('leaderboard.team', 'real-madrid');

        $this->assertStringContainsString('/leaderboard/team/real-madrid', $url);

        $response = $this->get($url);
        $response->assertOk();
    }

    public function test_service_get_teams_with_managers(): void
    {
        $team = Team::factory()->create(['name' => 'Test Club', 'slug' => 'test-club']);
        $user = User::factory()->create(['is_profile_public' => true]);

        ManagerStats::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'matches_played' => 20,
            'matches_won' => 10,
            'matches_drawn' => 5,
            'matches_lost' => 5,
            'win_percentage' => 50.00,
        ]);

        $service = app(LeaderboardService::class);
        $teams = $service->getTeamsWithManagers();

        $this->assertCount(1, $teams);
        $this->assertEquals('Test Club', $teams->first()->name);
        $this->assertEquals(1, $teams->first()->managers_count);
    }

    public function test_service_get_rankings_for_team(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create(['is_profile_public' => true]);

        ManagerStats::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'matches_played' => 20,
            'matches_won' => 15,
            'matches_drawn' => 3,
            'matches_lost' => 2,
            'win_percentage' => 75.00,
        ]);

        $service = app(LeaderboardService::class);
        $rankings = $service->getRankingsForTeam($team->id, 'win_percentage');

        $this->assertCount(1, $rankings);
    }

    public function test_service_get_team_aggregate_stats(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create(['is_profile_public' => true]);

        ManagerStats::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'matches_played' => 25,
            'matches_won' => 15,
            'matches_drawn' => 5,
            'matches_lost' => 5,
            'win_percentage' => 60.00,
        ]);

        $service = app(LeaderboardService::class);
        $stats = $service->getTeamAggregateStats($team->id);

        $this->assertEquals(1, $stats['totalManagers']);
        $this->assertEquals(25, $stats['totalMatches']);
    }
}
