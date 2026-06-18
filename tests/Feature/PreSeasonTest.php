<?php

namespace Tests\Feature;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\CompetitionTeam;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\Jobs\ProcessCareerActions;
use App\Modules\Season\Services\PreseasonOpponentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PreSeasonTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $playerTeam;
    private Competition $leagueCompetition;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->playerTeam = Team::factory()->create(['name' => 'Player Team', 'country' => 'ES']);
        ClubProfile::create([
            'team_id' => $this->playerTeam->id,
            'reputation_level' => ClubProfile::REPUTATION_ESTABLISHED,
        ]);

        $this->leagueCompetition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->playerTeam->id,
            'competition_id' => $this->leagueCompetition->id,
            'country' => 'ES',
            'season' => '2025',
            'current_date' => '2025-07-01',
            'pre_season' => true,
            'preseason_opponents_pending' => true,
            'setup_completed_at' => now(),
            'needs_new_season_setup' => false,
            'needs_welcome' => false,
        ]);
    }

    /**
     * Create an eligible (league-registered) team in the given country with a
     * matching reputation, so it surfaces in the candidate pool.
     */
    private function eligibleTeam(string $name, string $country, string $reputation, string $competitionId): Team
    {
        $team = Team::factory()->create(['name' => $name, 'country' => $country]);
        ClubProfile::create(['team_id' => $team->id, 'reputation_level' => $reputation]);

        $comp = Competition::firstOrCreate(
            ['id' => $competitionId],
            Competition::factory()->league()->raw(['id' => $competitionId, 'name' => $competitionId, 'country' => $country])
        );

        CompetitionTeam::create([
            'competition_id' => $comp->id,
            'team_id' => $team->id,
            'season' => '2025',
            'entry_round' => 1,
        ]);

        return $team;
    }

    public function test_game_reports_pre_season_status(): void
    {
        $this->assertTrue($this->game->isInPreSeason());

        $this->game->endPreSeason();
        $this->game->refresh();

        $this->assertFalse($this->game->isInPreSeason());
    }

    public function test_needs_preseason_opponent_selection_helper(): void
    {
        $this->assertTrue($this->game->needsPreseasonOpponentSelection());

        $this->game->update(['preseason_opponents_pending' => false]);
        $this->assertFalse($this->game->fresh()->needsPreseasonOpponentSelection());
    }

    public function test_show_game_redirects_to_preseason_setup_when_pending(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('show-game', $this->game->id));

        $response->assertRedirect(route('game.preseason-setup', $this->game->id));
    }

    public function test_candidate_pool_includes_eligible_foreign_teams_and_excludes_others(): void
    {
        $foreignMatch = $this->eligibleTeam('Foreign Match', 'GB', ClubProfile::REPUTATION_ESTABLISHED, 'ENG1');
        $domestic = $this->eligibleTeam('Domestic Side', 'ES', ClubProfile::REPUTATION_ESTABLISHED, 'ESP2');
        $foreignOutOfTier = $this->eligibleTeam('Foreign Giant', 'GB', ClubProfile::REPUTATION_ELITE, 'ENG2');

        $pool = app(PreseasonOpponentService::class)->candidatePool($this->game)->pluck('id');

        $this->assertContains($foreignMatch->id, $pool);
        $this->assertNotContains($domestic->id, $pool, 'Same-country teams are excluded');
        $this->assertNotContains($foreignOutOfTier->id, $pool, 'Out-of-tier teams are excluded');
    }

    public function test_confirm_selections_creates_chosen_friendlies(): void
    {
        $teamA = $this->eligibleTeam('Foreign A', 'GB', ClubProfile::REPUTATION_ESTABLISHED, 'ENG1');
        $teamB = $this->eligibleTeam('Foreign B', 'FR', ClubProfile::REPUTATION_MODEST, 'FRA1');

        app(PreseasonOpponentService::class)->confirmSelections($this->game, [
            ['slot' => 0, 'team_id' => $teamA->id, 'is_home' => true],
            ['slot' => 1, 'team_id' => $teamB->id, 'is_home' => false],
        ]);

        // Home friendly on slot 0 (July 12)
        $this->assertDatabaseHas('game_matches', [
            'game_id' => $this->game->id,
            'competition_id' => 'PRESEASON',
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $teamA->id,
            'scheduled_date' => '2025-07-12',
            'round_number' => 1,
        ]);

        // Away friendly on slot 1 (July 22)
        $this->assertDatabaseHas('game_matches', [
            'game_id' => $this->game->id,
            'competition_id' => 'PRESEASON',
            'home_team_id' => $teamB->id,
            'away_team_id' => $this->playerTeam->id,
            'scheduled_date' => '2025-07-22',
            'round_number' => 2,
        ]);

        $this->game->refresh();
        $this->assertTrue($this->game->isInPreSeason());
        $this->assertFalse($this->game->preseason_opponents_pending);
        $this->assertEquals('2025-07-12', $this->game->current_date->toDateString());
    }

    public function test_confirm_selections_with_no_picks_ends_pre_season(): void
    {
        Queue::fake();

        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP1',
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->eligibleTeam('Foreign A', 'GB', ClubProfile::REPUTATION_ESTABLISHED, 'ENG1')->id,
            'scheduled_date' => Carbon::parse('2025-08-17'),
            'played' => false,
            'round_number' => 1,
        ]);

        app(PreseasonOpponentService::class)->confirmSelections($this->game, []);

        $this->assertDatabaseMissing('game_matches', [
            'game_id' => $this->game->id,
            'competition_id' => 'PRESEASON',
        ]);

        $this->game->refresh();
        $this->assertFalse($this->game->isInPreSeason());
        $this->assertFalse($this->game->preseason_opponents_pending);
        $this->assertEquals('2025-08-17', $this->game->current_date->toDateString());

        Queue::assertPushed(ProcessCareerActions::class);
    }

    public function test_save_preseason_opponents_route_confirms_selection(): void
    {
        Queue::fake();

        $teamA = $this->eligibleTeam('Foreign A', 'GB', ClubProfile::REPUTATION_ESTABLISHED, 'ENG1');

        $response = $this->actingAs($this->user)->post(
            route('game.preseason-setup.save', $this->game->id),
            ['slots' => [0 => ['team_id' => $teamA->id, 'is_home' => '1']]]
        );

        $response->assertRedirect(route('show-game', $this->game->id));

        $this->assertDatabaseHas('game_matches', [
            'game_id' => $this->game->id,
            'competition_id' => 'PRESEASON',
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $teamA->id,
        ]);

        $this->assertFalse($this->game->fresh()->preseason_opponents_pending);
    }

    public function test_show_game_passes_pre_season_data_once_opponents_selected(): void
    {
        // Opponents already chosen — no longer pending, so the dashboard renders.
        $this->game->update(['preseason_opponents_pending' => false]);

        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP1',
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->eligibleTeam('Foreign A', 'GB', ClubProfile::REPUTATION_ESTABLISHED, 'ENG1')->id,
            'scheduled_date' => Carbon::parse('2025-08-17'),
            'played' => false,
            'round_number' => 1,
        ]);

        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'PRESEASON',
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->eligibleTeam('Foreign B', 'FR', ClubProfile::REPUTATION_MODEST, 'FRA1')->id,
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
