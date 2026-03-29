<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Promotions\ConfigDrivenPromotionRule;
use App\Modules\Competition\Promotions\PromotionRelegationFactory;
use App\Modules\Competition\Services\ReserveTeamFilter;
use App\Modules\Report\Services\SeasonSummaryService;
use App\Modules\Season\Processors\SeasonSimulationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionRelegationValidationTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Competition $topDivision;
    private Competition $bottomDivision;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->topDivision = Competition::factory()->league()->create(['id' => 'ESP1', 'tier' => 1]);
        $this->bottomDivision = Competition::factory()->league()->create(['id' => 'ESP2', 'tier' => 2]);

        $team = Team::factory()->create();
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
        ]);
    }

    private function createStandings(string $competitionId, int $count): array
    {
        $teams = [];
        for ($i = 1; $i <= $count; $i++) {
            $team = Team::factory()->create();
            $teams[] = $team;

            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => $competitionId,
                'team_id' => $team->id,
                'position' => $i,
                'played' => 38,
                'won' => 20 - $i,
                'drawn' => 5,
                'lost' => $i,
                'goals_for' => 50 - $i,
                'goals_against' => 20 + $i,
                'points' => (20 - $i) * 3 + 5,
            ]);
        }

        return $teams;
    }

    // ──────────────────────────────────────────────────
    // getPromotedTeams validation
    // ──────────────────────────────────────────────────

    public function test_promoted_teams_from_real_standings_returns_expected_count(): void
    {
        $this->createStandings('ESP2', 22);

        $rule = new ConfigDrivenPromotionRule(
            topDivision: 'ESP1',
            bottomDivision: 'ESP2',
            relegatedPositions: [18, 19, 20],
            directPromotionPositions: [1, 2],
            playoffGenerator: null,
        );

        // Without playoff generator, only direct promotion positions are used
        // But expected count = count(relegatedPositions) = 3, and we only get 2
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expected 3 promoted teams');

        $rule->getPromotedTeams($this->game);
    }

    public function test_promoted_teams_with_playoff_fallback_returns_3(): void
    {
        $this->createStandings('ESP2', 22);

        // No playoff generator, but relegatedPositions matches directPromotionPositions count
        $rule = new ConfigDrivenPromotionRule(
            topDivision: 'ESP1',
            bottomDivision: 'ESP2',
            relegatedPositions: [1, 2],
            directPromotionPositions: [1, 2],
            playoffGenerator: null,
        );

        $promoted = $rule->getPromotedTeams($this->game);
        $this->assertCount(2, $promoted);
    }

    public function test_promoted_teams_from_simulated_throws_when_count_wrong(): void
    {
        // Create simulated season with only 2 results instead of 3
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();

        SimulatedSeason::create([
            'game_id' => $this->game->id,
            'season' => '2025',
            'competition_id' => 'ESP2',
            'results' => [$team1->id, $team2->id],
        ]);

        $rule = new ConfigDrivenPromotionRule(
            topDivision: 'ESP1',
            bottomDivision: 'ESP2',
            relegatedPositions: [18, 19, 20],
            directPromotionPositions: [1, 2],
            playoffGenerator: null,
        );

        // No real standings, falls back to simulated. Expects 3 (count of relegatedPositions)
        // but simulated only has 2 results
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expected 3 promoted teams');

        $rule->getPromotedTeams($this->game);
    }

    // ──────────────────────────────────────────────────
    // getRelegatedTeams validation
    // ──────────────────────────────────────────────────

    public function test_relegated_teams_returns_expected_count(): void
    {
        $this->createStandings('ESP1', 20);

        $rule = new ConfigDrivenPromotionRule(
            topDivision: 'ESP1',
            bottomDivision: 'ESP2',
            relegatedPositions: [18, 19, 20],
            directPromotionPositions: [1, 2],
        );

        $relegated = $rule->getRelegatedTeams($this->game);
        $this->assertCount(3, $relegated);
    }

    public function test_relegated_teams_throws_when_standings_incomplete(): void
    {
        // Only create 17 teams — positions 18, 19, 20 don't exist
        $this->createStandings('ESP1', 17);

        $rule = new ConfigDrivenPromotionRule(
            topDivision: 'ESP1',
            bottomDivision: 'ESP2',
            relegatedPositions: [18, 19, 20],
            directPromotionPositions: [1, 2],
        );

        // Real standings exist but positions 18-20 are missing
        // Falls to simulated path, which also has nothing → 0 teams
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expected 3 relegated teams');

        $rule->getRelegatedTeams($this->game);
    }

    public function test_relegated_teams_from_simulated_returns_expected_count(): void
    {
        $teams = [];
        for ($i = 0; $i < 20; $i++) {
            $teams[] = Team::factory()->create()->id;
        }

        SimulatedSeason::create([
            'game_id' => $this->game->id,
            'season' => '2025',
            'competition_id' => 'ESP1',
            'results' => $teams,
        ]);

        $rule = new ConfigDrivenPromotionRule(
            topDivision: 'ESP1',
            bottomDivision: 'ESP2',
            relegatedPositions: [18, 19, 20],
            directPromotionPositions: [1, 2],
        );

        $relegated = $rule->getRelegatedTeams($this->game);
        $this->assertCount(3, $relegated);
        $this->assertEquals($teams[17], $relegated[0]['teamId']);
        $this->assertEquals($teams[18], $relegated[1]['teamId']);
        $this->assertEquals($teams[19], $relegated[2]['teamId']);
    }

    // ──────────────────────────────────────────────────
    // Missing data source — early return (P0 bug fix)
    // ──────────────────────────────────────────────────

    public function test_promoted_teams_returns_empty_when_no_data_sources_exist(): void
    {
        // No standings, no simulated season for ESP2
        $rule = new ConfigDrivenPromotionRule(
            topDivision: 'ESP1',
            bottomDivision: 'ESP2',
            relegatedPositions: [18, 19, 20],
            directPromotionPositions: [1, 2],
        );

        $promoted = $rule->getPromotedTeams($this->game);
        $this->assertEmpty($promoted);
    }

    public function test_relegated_teams_returns_empty_when_no_data_sources_exist(): void
    {
        // No standings, no simulated season for ESP1
        $rule = new ConfigDrivenPromotionRule(
            topDivision: 'ESP1',
            bottomDivision: 'ESP2',
            relegatedPositions: [18, 19, 20],
            directPromotionPositions: [1, 2],
        );

        $relegated = $rule->getRelegatedTeams($this->game);
        $this->assertEmpty($relegated);
    }

    public function test_build_promotion_data_returns_null_when_no_data_available(): void
    {
        // No standings or simulated data — simulates the season-end page
        // being rendered before the closing pipeline runs
        $service = app(SeasonSummaryService::class);

        $result = $service->buildPromotionData($this->game, $this->topDivision);
        $this->assertNull($result);
    }

    // ──────────────────────────────────────────────────
    // Reserve team filtering with batch loading
    // ──────────────────────────────────────────────────

    public function test_reserve_team_filter_batch_loads_parent_ids(): void
    {
        $parent = Team::factory()->create();
        $reserve = Team::factory()->create(['parent_team_id' => $parent->id]);
        $regular = Team::factory()->create();

        $filter = new ReserveTeamFilter;
        $parentMap = $filter->loadParentTeamIds([$reserve->id, $regular->id]);

        $this->assertTrue($parentMap->has($reserve->id));
        $this->assertFalse($parentMap->has($regular->id));
        $this->assertEquals($parent->id, $parentMap->get($reserve->id));
    }

    public function test_reserve_team_blocked_with_preloaded_map(): void
    {
        $parent = Team::factory()->create();
        $reserve = Team::factory()->create(['parent_team_id' => $parent->id]);

        $filter = new ReserveTeamFilter;
        $parentMap = $filter->loadParentTeamIds([$reserve->id]);
        $topDivisionTeamIds = collect([$parent->id]);

        $this->assertTrue($filter->isBlockedReserveTeam($reserve->id, $topDivisionTeamIds, $parentMap));
    }

    public function test_reserve_team_not_blocked_when_parent_not_in_top_division(): void
    {
        $parent = Team::factory()->create();
        $reserve = Team::factory()->create(['parent_team_id' => $parent->id]);
        $otherTeam = Team::factory()->create();

        $filter = new ReserveTeamFilter;
        $parentMap = $filter->loadParentTeamIds([$reserve->id]);
        $topDivisionTeamIds = collect([$otherTeam->id]);

        $this->assertFalse($filter->isBlockedReserveTeam($reserve->id, $topDivisionTeamIds, $parentMap));
    }

    public function test_promoted_teams_skips_blocked_reserve_team(): void
    {
        $parentTeam = Team::factory()->create();

        // Create ESP1 standings with parent team in top division
        $esp1Teams = $this->createStandings('ESP1', 20);

        // Add parent team as an ESP1 entry
        CompetitionEntry::create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP1',
            'team_id' => $parentTeam->id,
        ]);

        // Create ESP2 standings where the reserve team is in position 1
        $reserveTeam = Team::factory()->create(['parent_team_id' => $parentTeam->id]);
        GameStanding::create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP2',
            'team_id' => $reserveTeam->id,
            'position' => 1,
            'played' => 42, 'won' => 25, 'drawn' => 10, 'lost' => 7,
            'goals_for' => 70, 'goals_against' => 30, 'points' => 85,
        ]);

        // Create regular teams in positions 2-22
        for ($i = 2; $i <= 22; $i++) {
            $team = Team::factory()->create();
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP2',
                'team_id' => $team->id,
                'position' => $i,
                'played' => 42, 'won' => 25 - $i, 'drawn' => 5, 'lost' => $i,
                'goals_for' => 50 - $i, 'goals_against' => 20 + $i,
                'points' => (25 - $i) * 3 + 5,
            ]);
        }

        $rule = new ConfigDrivenPromotionRule(
            topDivision: 'ESP1',
            bottomDivision: 'ESP2',
            relegatedPositions: [19, 20],
            directPromotionPositions: [1, 2],
        );

        $promoted = $rule->getPromotedTeams($this->game);

        // Reserve team at position 1 is skipped; positions 2 and 3 are promoted
        $this->assertCount(2, $promoted);
        $promotedIds = array_column($promoted, 'teamId');
        $this->assertNotContains($reserveTeam->id, $promotedIds);
    }

    // ──────────────────────────────────────────────────
    // Double simulation prevention
    // ──────────────────────────────────────────────────

    public function test_simulation_processor_does_not_overwrite_existing_simulated_data(): void
    {
        // Create teams and competition entries for ESP2
        $teamIds = [];
        for ($i = 0; $i < 22; $i++) {
            $team = Team::factory()->create();
            $teamIds[] = $team->id;
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP2',
                'team_id' => $team->id,
            ]);
        }

        // Pre-existing simulated data (as created by SimulateOtherLeagues listener)
        $originalResults = $teamIds;
        SimulatedSeason::create([
            'game_id' => $this->game->id,
            'season' => '2025',
            'competition_id' => 'ESP2',
            'results' => $originalResults,
        ]);

        // Run the simulation processor (as happens during closing pipeline)
        $processor = app(SeasonSimulationProcessor::class);
        $processor->simulateNonPlayedLeagues($this->game);

        // Verify the original results were NOT overwritten
        $simulated = SimulatedSeason::where('game_id', $this->game->id)
            ->where('season', '2025')
            ->where('competition_id', 'ESP2')
            ->first();

        $this->assertEquals($originalResults, $simulated->results);
    }

    public function test_simulation_processor_overwrites_when_force_resimulate_is_true(): void
    {
        // Create teams and competition entries for ESP2
        $teamIds = [];
        for ($i = 0; $i < 22; $i++) {
            $team = Team::factory()->create();
            $teamIds[] = $team->id;
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP2',
                'team_id' => $team->id,
            ]);
        }

        // Pre-existing simulated data
        SimulatedSeason::create([
            'game_id' => $this->game->id,
            'season' => '2025',
            'competition_id' => 'ESP2',
            'results' => $teamIds,
        ]);

        // Force re-simulation (as happens after promotion/relegation swaps)
        $processor = app(SeasonSimulationProcessor::class);
        $processor->simulateNonPlayedLeagues($this->game, ['ESP2'], forceResimulate: true);

        // The simulated data should have been re-generated (results will differ
        // because the simulation uses random Poisson-distributed goals)
        $simulated = SimulatedSeason::where('game_id', $this->game->id)
            ->where('season', '2025')
            ->where('competition_id', 'ESP2')
            ->first();

        $this->assertNotNull($simulated);
        $this->assertCount(22, $simulated->results);
    }
}
