<?php

namespace Tests\Feature;

use App\Models\AcademyPlayer;
use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameInvestment;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\Player;
use App\Models\ScoutReport;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $playerTeam;
    private Team $opponentTeam;
    private Competition $league;
    private Competition $cup;
    private Game $game;
    private GameMatch $nextMatch;
    private GameMatch $playedMatch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->playerTeam = Team::factory()->create(['name' => 'Player Team']);
        $this->opponentTeam = Team::factory()->create(['name' => 'Opponent Team']);

        $this->league = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
            'season' => '2025',
        ]);

        $this->cup = Competition::factory()->knockoutCup()->create([
            'id' => 'ESPCUP',
            'name' => 'Copa del Rey',
            'season' => '2025',
        ]);

        // Link teams to competitions via pivot
        $this->playerTeam->competitions()->attach($this->league->id, ['season' => '2025']);
        $this->opponentTeam->competitions()->attach($this->league->id, ['season' => '2025']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->playerTeam->id,
            'competition_id' => $this->league->id,
            'season' => '2025',
            'current_date' => '2025-09-15',
            'setup_completed_at' => now(),
            'needs_welcome' => false,
            'needs_new_season_setup' => false,
            'game_mode' => 'career',
        ]);

        // Competition entries
        CompetitionEntry::create([
            'game_id' => $this->game->id,
            'competition_id' => $this->league->id,
            'team_id' => $this->playerTeam->id,
        ]);
        CompetitionEntry::create([
            'game_id' => $this->game->id,
            'competition_id' => $this->league->id,
            'team_id' => $this->opponentTeam->id,
        ]);

        // Standings
        foreach ([$this->playerTeam, $this->opponentTeam] as $team) {
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => $this->league->id,
                'team_id' => $team->id,
                'position' => $team->id === $this->playerTeam->id ? 1 : 2,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
            ]);
        }

        // Finances and investment
        GameFinances::create([
            'game_id' => $this->game->id,
            'season' => 2025,
            'projected_total_revenue' => 50_000_000_00,
            'projected_wages' => 30_000_000_00,
            'projected_surplus' => 20_000_000_00,
            'projected_tv_revenue' => 20_000_000_00,
            'projected_matchday_revenue' => 10_000_000_00,
            'projected_commercial_revenue' => 15_000_000_00,
            'projected_operating_expenses' => 5_000_000_00,
            'projected_taxes' => 0,
            'projected_solidarity_funds_revenue' => 0,
            'projected_subsidy_revenue' => 0,
        ]);

        GameInvestment::create([
            'game_id' => $this->game->id,
            'season' => 2025,
            'available_surplus' => 20_000_000_00,
            'transfer_budget' => 10_000_000_00,
            'youth_academy_amount' => 200_000_000,
            'youth_academy_tier' => 2,
            'medical_amount' => 150_000_000,
            'medical_tier' => 2,
            'scouting_amount' => 100_000_000,
            'scouting_tier' => 2,
            'facilities_amount' => 300_000_000,
            'facilities_tier' => 2,
        ]);

        // Team reputation
        TeamReputation::create([
            'game_id' => $this->game->id,
            'team_id' => $this->playerTeam->id,
            'reputation_level' => ClubProfile::REPUTATION_ESTABLISHED,
            'base_reputation_level' => ClubProfile::REPUTATION_ESTABLISHED,
            'reputation_points' => 200,
        ]);
        TeamReputation::create([
            'game_id' => $this->game->id,
            'team_id' => $this->opponentTeam->id,
            'reputation_level' => ClubProfile::REPUTATION_MODEST,
            'base_reputation_level' => ClubProfile::REPUTATION_MODEST,
            'reputation_points' => 100,
        ]);

        // Create players for both teams (11+ each with varied positions)
        $this->createSquad($this->playerTeam);
        $this->createSquad($this->opponentTeam);

        // Next match (unplayed)
        $this->nextMatch = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->league->id,
            'round_number' => 1,
            'round_name' => 'Matchday 1',
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2025-09-20'),
            'played' => false,
        ]);

        // Played match (for results pages)
        $this->playedMatch = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->league->id,
            'round_number' => 2,
            'round_name' => 'Matchday 2',
            'home_team_id' => $this->opponentTeam->id,
            'away_team_id' => $this->playerTeam->id,
            'scheduled_date' => Carbon::parse('2025-09-13'),
            'played' => true,
            'home_score' => 1,
            'away_score' => 2,
        ]);
    }

    private function createSquad(Team $team, ?Game $game = null): void
    {
        $gameId = ($game ?? $this->game)->id;

        $positions = [
            'Goalkeeper', 'Goalkeeper',
            'Centre-Back', 'Centre-Back', 'Centre-Back', 'Centre-Back',
            'Left-Back', 'Right-Back',
            'Central Midfield', 'Central Midfield', 'Central Midfield',
            'Attacking Midfield',
            'Left Winger', 'Right Winger',
            'Centre-Forward', 'Centre-Forward',
        ];

        foreach ($positions as $position) {
            $player = Player::factory()->create();
            GamePlayer::factory()->create([
                'game_id' => $gameId,
                'player_id' => $player->id,
                'team_id' => $team->id,
                'position' => $position,
            ]);
        }
    }

    // =============================================
    // Public routes
    // =============================================

    public function test_legal_page_loads(): void
    {
        $this->get('/legal')->assertOk();
    }

    public function test_design_system_page_loads(): void
    {
        $this->get('/design-system')->assertOk();
    }

    // =============================================
    // Auth-only routes
    // =============================================

    public function test_dashboard_loads(): void
    {
        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_new_game_page_loads(): void
    {
        $this->actingAs($this->user)
            ->get('/new-game')
            ->assertOk();
    }

    // =============================================
    // Core game routes
    // =============================================

    public function test_show_game_loads(): void
    {
        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}")
            ->assertOk();
    }

    public function test_squad_page_loads(): void
    {
        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/squad")
            ->assertOk();
    }

    public function test_transfers_page_loads(): void
    {
        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/transfers")
            ->assertOk();
    }

    public function test_outgoing_transfers_page_loads(): void
    {
        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/transfers/outgoing")
            ->assertOk();
    }

    public function test_transfer_activity_page_redirects_when_no_activity(): void
    {
        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/transfer-activity")
            ->assertRedirect();
    }

    public function test_scouting_page_loads(): void
    {
        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/scouting")
            ->assertOk();
    }

    public function test_calendar_page_loads(): void
    {
        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/calendar")
            ->assertOk();
    }

    public function test_finances_page_loads(): void
    {
        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/finances")
            ->assertOk();
    }

    public function test_explore_page_loads(): void
    {
        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/explore")
            ->assertOk();
    }

    // =============================================
    // Competition & results
    // =============================================

    public function test_competition_page_loads(): void
    {
        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/competition/{$this->league->id}")
            ->assertOk();
    }

    public function test_match_results_page_loads(): void
    {
        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/results/{$this->league->id}/2")
            ->assertOk();
    }

    // =============================================
    // Lineup
    // =============================================

    public function test_lineup_page_loads(): void
    {
        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/lineup")
            ->assertOk();
    }

    // =============================================
    // Player detail
    // =============================================

    public function test_player_detail_page_loads(): void
    {
        $player = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->first();

        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/player/{$player->id}/detail")
            ->assertOk();
    }

    // =============================================
    // Budget allocation
    // =============================================

    public function test_budget_page_loads(): void
    {
        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/budget")
            ->assertOk();
    }

    // =============================================
    // Academy
    // =============================================

    public function test_academy_page_loads(): void
    {
        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/squad/academy")
            ->assertOk();
    }

    public function test_academy_player_detail_loads(): void
    {
        $academyPlayer = AcademyPlayer::create([
            'game_id' => $this->game->id,
            'team_id' => $this->playerTeam->id,
            'name' => 'Youth Player',
            'nationality' => ['ESP'],
            'date_of_birth' => '2008-03-15',
            'position' => 'Central Midfield',
            'technical_ability' => 45,
            'physical_ability' => 40,
            'potential' => 75,
            'potential_low' => 65,
            'potential_high' => 85,
            'appeared_at' => '2025-07-01',
            'joined_season' => '2025',
            'initial_technical' => 45,
            'initial_physical' => 40,
        ]);

        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/academy/{$academyPlayer->id}/detail")
            ->assertOk();
    }

    // =============================================
    // Welcome & new-season setup (special game state)
    // =============================================

    public function test_welcome_page_loads(): void
    {
        $this->game->update(['needs_welcome' => true]);

        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/welcome")
            ->assertOk();
    }

    public function test_new_season_page_loads(): void
    {
        $this->game->update(['needs_new_season_setup' => true]);

        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/new-season")
            ->assertOk();
    }

    // =============================================
    // Season end (no unplayed matches)
    // =============================================

    public function test_season_end_page_loads(): void
    {
        // Use a generic league ID to avoid ESP1 promotion/relegation validation
        $seasonEndLeague = Competition::factory()->league()->create([
            'id' => 'TEST1',
            'name' => 'Test League',
            'season' => '2025',
            'country' => 'XX',
        ]);

        $seasonEndGame = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->playerTeam->id,
            'competition_id' => $seasonEndLeague->id,
            'season' => '2025',
            'current_date' => '2025-06-15',
            'setup_completed_at' => now(),
            'needs_welcome' => false,
            'needs_new_season_setup' => false,
            'game_mode' => 'career',
        ]);

        CompetitionEntry::create([
            'game_id' => $seasonEndGame->id,
            'competition_id' => $seasonEndLeague->id,
            'team_id' => $this->playerTeam->id,
        ]);
        CompetitionEntry::create([
            'game_id' => $seasonEndGame->id,
            'competition_id' => $seasonEndLeague->id,
            'team_id' => $this->opponentTeam->id,
        ]);

        foreach ([$this->playerTeam, $this->opponentTeam] as $idx => $team) {
            GameStanding::create([
                'game_id' => $seasonEndGame->id,
                'competition_id' => $seasonEndLeague->id,
                'team_id' => $team->id,
                'position' => $idx + 1,
                'played' => 2,
                'won' => $idx === 0 ? 2 : 0,
                'drawn' => 0,
                'lost' => $idx === 0 ? 0 : 2,
                'goals_for' => $idx === 0 ? 4 : 1,
                'goals_against' => $idx === 0 ? 1 : 4,
                'points' => $idx === 0 ? 6 : 0,
            ]);
        }

        GameFinances::create([
            'game_id' => $seasonEndGame->id,
            'season' => 2025,
            'projected_total_revenue' => 50_000_000_00,
            'projected_wages' => 30_000_000_00,
            'projected_surplus' => 20_000_000_00,
            'projected_tv_revenue' => 20_000_000_00,
            'projected_matchday_revenue' => 10_000_000_00,
            'projected_commercial_revenue' => 15_000_000_00,
            'projected_operating_expenses' => 5_000_000_00,
            'projected_taxes' => 0,
            'projected_solidarity_funds_revenue' => 0,
            'projected_subsidy_revenue' => 0,
        ]);

        // All matches played — season complete
        GameMatch::factory()->create([
            'game_id' => $seasonEndGame->id,
            'competition_id' => $seasonEndLeague->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2025-09-20'),
            'played' => true,
            'home_score' => 2,
            'away_score' => 0,
        ]);
        GameMatch::factory()->create([
            'game_id' => $seasonEndGame->id,
            'competition_id' => $seasonEndLeague->id,
            'round_number' => 2,
            'home_team_id' => $this->opponentTeam->id,
            'away_team_id' => $this->playerTeam->id,
            'scheduled_date' => Carbon::parse('2025-10-20'),
            'played' => true,
            'home_score' => 1,
            'away_score' => 2,
        ]);

        $this->createSquad($this->playerTeam, $seasonEndGame);
        $this->createSquad($this->opponentTeam, $seasonEndGame);

        $this->actingAs($this->user)
            ->get("/game/{$seasonEndGame->id}/season-end")
            ->assertOk();
    }

    // =============================================
    // Explore sub-pages
    // =============================================

    public function test_explore_teams_returns_json(): void
    {
        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/explore/teams/{$this->league->id}")
            ->assertOk();
    }

    public function test_explore_squad_returns_partial(): void
    {
        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/explore/squad/{$this->opponentTeam->id}")
            ->assertOk();
    }

    // =============================================
    // Scout report results
    // =============================================

    public function test_scout_report_results_loads(): void
    {
        $scoutedPlayer = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->opponentTeam->id)
            ->first();

        $report = ScoutReport::create([
            'game_id' => $this->game->id,
            'status' => ScoutReport::STATUS_COMPLETED,
            'filters' => ['position' => 'Central Midfield'],
            'weeks_total' => 2,
            'weeks_remaining' => 0,
            'player_ids' => [$scoutedPlayer->id],
            'game_date' => '2025-09-01',
        ]);

        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/scouting/{$report->id}/results")
            ->assertOk();
    }

    // =============================================
    // Live match
    // =============================================

    public function test_live_match_redirects_when_not_pending(): void
    {
        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/live/{$this->playedMatch->id}")
            ->assertRedirect();
    }

    public function test_live_match_loads_when_pending(): void
    {
        // Set the played match as pending finalization
        $this->game->update([
            'pending_finalization_match_id' => $this->playedMatch->id,
        ]);

        $this->actingAs($this->user)
            ->get("/game/{$this->game->id}/live/{$this->playedMatch->id}")
            ->assertOk();
    }

    // =============================================
    // Setup status (JSON polling endpoint)
    // =============================================

    public function test_setup_status_returns_json(): void
    {
        $this->actingAs($this->user)
            ->getJson("/game/{$this->game->id}/setup-status")
            ->assertOk()
            ->assertJsonStructure(['ready']);
    }

    // =============================================
    // Admin
    // =============================================

    public function test_admin_users_page_loads(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk();
    }

    // =============================================
    // Tournament mode routes
    // =============================================

    public function test_squad_selection_redirects_for_tournament(): void
    {
        // Squad selection auto-selects and redirects when there are ≤26 candidates
        // (which happens when the JSON roster file doesn't exist or has few players)
        $tournamentComp = Competition::factory()->league()->create([
            'id' => 'WC2026',
            'name' => 'World Cup 2026',
            'season' => '2025',
            'country' => 'IN',
        ]);

        $tournamentTeam = Team::factory()->create([
            'name' => 'Spain',
            'transfermarkt_id' => 12345,
        ]);

        $tournamentGame = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $tournamentTeam->id,
            'competition_id' => $tournamentComp->id,
            'season' => '2025',
            'current_date' => '2026-06-01',
            'setup_completed_at' => now(),
            'needs_welcome' => false,
            'needs_new_season_setup' => true,
            'game_mode' => 'tournament',
        ]);

        CompetitionEntry::create([
            'game_id' => $tournamentGame->id,
            'competition_id' => $tournamentComp->id,
            'team_id' => $tournamentTeam->id,
        ]);

        $this->actingAs($this->user)
            ->get("/game/{$tournamentGame->id}/squad-selection")
            ->assertRedirect();
    }

    public function test_tournament_end_loads(): void
    {
        $tournamentComp = Competition::factory()->knockoutCup()->create([
            'id' => 'TOURN1',
            'name' => 'Tournament',
            'season' => '2025',
        ]);

        $tournamentGame = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->playerTeam->id,
            'competition_id' => $tournamentComp->id,
            'season' => '2025',
            'current_date' => '2025-07-15',
            'setup_completed_at' => now(),
            'needs_welcome' => false,
            'needs_new_season_setup' => false,
            'game_mode' => 'tournament',
        ]);

        CompetitionEntry::create([
            'game_id' => $tournamentGame->id,
            'competition_id' => $tournamentComp->id,
            'team_id' => $this->playerTeam->id,
        ]);
        CompetitionEntry::create([
            'game_id' => $tournamentGame->id,
            'competition_id' => $tournamentComp->id,
            'team_id' => $this->opponentTeam->id,
        ]);

        // Standings for tournament
        foreach ([$this->playerTeam, $this->opponentTeam] as $idx => $team) {
            GameStanding::create([
                'game_id' => $tournamentGame->id,
                'competition_id' => $tournamentComp->id,
                'team_id' => $team->id,
                'position' => $idx + 1,
                'played' => 1,
                'won' => $idx === 0 ? 1 : 0,
                'drawn' => 0,
                'lost' => $idx === 0 ? 0 : 1,
                'goals_for' => $idx === 0 ? 2 : 0,
                'goals_against' => $idx === 0 ? 0 : 2,
                'points' => $idx === 0 ? 3 : 0,
                'group_label' => 'A',
            ]);
        }

        // No unplayed matches — tournament is complete
        GameMatch::factory()->create([
            'game_id' => $tournamentGame->id,
            'competition_id' => $tournamentComp->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2025-07-10'),
            'played' => true,
            'home_score' => 2,
            'away_score' => 0,
        ]);

        $this->actingAs($this->user)
            ->get("/game/{$tournamentGame->id}/tournament-end")
            ->assertOk();
    }

    // =============================================
    // Profile
    // =============================================

    public function test_profile_page_loads(): void
    {
        $this->actingAs($this->user)
            ->get('/profile')
            ->assertOk();
    }

}
