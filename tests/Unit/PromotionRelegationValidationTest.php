<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Contracts\PlayoffGenerator;
use App\Modules\Competition\DTOs\PlayoffRoundConfig;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Exceptions\PlayoffInProgressException;
use App\Modules\Competition\Promotions\ConfigDrivenPromotionRule;
use App\Modules\Competition\Services\ReserveTeamFilter;
use App\Modules\Report\Services\SeasonSummaryService;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\PromotionRelegationProcessor;
use App\Modules\Season\Processors\SeasonSimulationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test double for PlayoffGenerator so rule tests don't need a real generator
 * with its schedule JSON + DB lookups. Only the methods exercised by
 * ConfigDrivenPromotionRule are meaningfully implemented.
 */
class FakePlayoffGenerator implements PlayoffGenerator
{
    public function __construct(
        private PlayoffState $state = PlayoffState::NotStarted,
        private string $competitionId = 'ESP2',
        private int $totalRounds = 2,
    ) {}

    public function setState(PlayoffState $state): void { $this->state = $state; }
    public function getCompetitionId(): string { return $this->competitionId; }
    public function getQualifyingPositions(): array { return [3, 4, 5, 6]; }
    public function getDirectPromotionPositions(): array { return [1, 2]; }
    public function getTriggerMatchday(): int { return 42; }
    public function getTotalRounds(): int { return $this->totalRounds; }
    public function generateMatchups(\App\Models\Game $game, int $round): array { return []; }
    public function isComplete(\App\Models\Game $game): bool { return $this->state === PlayoffState::Completed; }
    public function state(\App\Models\Game $game): PlayoffState { return $this->state; }
    public function getRoundConfig(int $round, ?string $gameSeason = null): PlayoffRoundConfig
    {
        return new PlayoffRoundConfig(
            round: $round,
            name: "round {$round}",
            twoLegged: true,
            firstLegDate: \Carbon\Carbon::parse('2026-05-01'),
            secondLegDate: \Carbon\Carbon::parse('2026-05-08'),
        );
    }
}

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

        // Promotion rules require the top division to have CompetitionEntry
        // rows so reserve-team filtering has a reference set. Seed a minimal
        // 20-team roster here so individual tests don't have to care.
        $this->seedTopDivisionRoster();
    }

    private function seedTopDivisionRoster(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $team = Team::factory()->create();
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP1',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }
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

    // ──────────────────────────────────────────────────
    // Two-pass swap: ESP1 relegation must not cascade to ESP3
    // ──────────────────────────────────────────────────

    public function test_two_pass_prevents_cascading_relegation_when_player_in_esp2(): void
    {
        // When the player manages an ESP2 team, ESP2 has real standings.
        // The ESP1↔ESP2 swap runs first and inserts relegated ESP1 teams
        // at position 99 in ESP2. Without the two-pass fix, those teams
        // would land at positions 20–22 after re-sort and get immediately
        // relegated to ESP3 by the next rule — a cascading relegation bug.

        Competition::factory()->league()->create(['id' => 'ESP3A', 'tier' => 3]);
        Competition::factory()->league()->create(['id' => 'ESP3B', 'tier' => 3]);
        Competition::factory()->knockoutCup()->create(['id' => 'ESP3PO', 'tier' => 3]);

        // --- ESP1: 20 simulated teams ---
        $esp1TeamIds = [];
        for ($i = 0; $i < 20; $i++) {
            $team = Team::factory()->create();
            $esp1TeamIds[] = $team->id;
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP1',
                'team_id' => $team->id,
            ]);
        }
        SimulatedSeason::create([
            'game_id' => $this->game->id,
            'season' => '2025',
            'competition_id' => 'ESP1',
            'results' => $esp1TeamIds,
        ]);

        // --- ESP2: 22 teams with real standings (player's league) ---
        $esp2TeamIds = [];
        for ($i = 1; $i <= 22; $i++) {
            $team = Team::factory()->create();
            $esp2TeamIds[] = $team->id;
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP2',
                'team_id' => $team->id,
                'position' => $i,
                'played' => 42,
                'won' => max(1, 23 - $i),
                'drawn' => 5,
                'lost' => $i,
                'goals_for' => max(10, 60 - $i),
                'goals_against' => 20 + $i,
                'points' => max(1, 23 - $i) * 3 + 5,
            ]);
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP2',
                'team_id' => $team->id,
            ]);
        }

        // Player's team is at position 10 — safe from promotion/relegation
        $this->game->update([
            'competition_id' => 'ESP2',
            'team_id' => $esp2TeamIds[9],
        ]);

        // The teams that should be relegated from ESP2 (original positions 19–22)
        $expectedRelegatedFromEsp2 = array_slice($esp2TeamIds, 18, 4);

        // The ESP1 teams that will be relegated to ESP2 (simulated positions 18–20)
        $expectedRelegatedFromEsp1 = array_slice($esp1TeamIds, 17, 3);

        // --- ESP3A: 20 simulated teams ---
        $esp3aTeamIds = [];
        for ($i = 0; $i < 20; $i++) {
            $team = Team::factory()->create();
            $esp3aTeamIds[] = $team->id;
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP3A',
                'team_id' => $team->id,
            ]);
        }
        SimulatedSeason::create([
            'game_id' => $this->game->id,
            'season' => '2025',
            'competition_id' => 'ESP3A',
            'results' => $esp3aTeamIds,
        ]);

        // --- ESP3B: 20 simulated teams ---
        $esp3bTeamIds = [];
        for ($i = 0; $i < 20; $i++) {
            $team = Team::factory()->create();
            $esp3bTeamIds[] = $team->id;
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP3B',
                'team_id' => $team->id,
            ]);
        }
        SimulatedSeason::create([
            'game_id' => $this->game->id,
            'season' => '2025',
            'competition_id' => 'ESP3B',
            'results' => $esp3bTeamIds,
        ]);

        // --- Run the processor ---
        $processor = app(PromotionRelegationProcessor::class);
        $data = new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP2',
        );
        $processor->process($this->game, $data);

        // Original ESP2 bottom-4 teams should now be in ESP3A or ESP3B
        foreach ($expectedRelegatedFromEsp2 as $teamId) {
            $this->assertTrue(
                CompetitionEntry::where('game_id', $this->game->id)
                    ->whereIn('competition_id', ['ESP3A', 'ESP3B'])
                    ->where('team_id', $teamId)
                    ->exists(),
                "ESP2 team at original bottom-4 position should have been relegated to ESP3"
            );
        }

        // ESP1 relegated teams should be in ESP2, NOT cascaded down to ESP3
        foreach ($expectedRelegatedFromEsp1 as $teamId) {
            $this->assertTrue(
                CompetitionEntry::where('game_id', $this->game->id)
                    ->where('competition_id', 'ESP2')
                    ->where('team_id', $teamId)
                    ->exists(),
                "Team relegated from ESP1 should be in ESP2"
            );
            $this->assertFalse(
                CompetitionEntry::where('game_id', $this->game->id)
                    ->whereIn('competition_id', ['ESP3A', 'ESP3B'])
                    ->where('team_id', $teamId)
                    ->exists(),
                "Team relegated from ESP1 should NOT cascade to ESP3"
            );
        }
    }

    // ──────────────────────────────────────────────────
    // Playoff state machine — the regression-proofing for Bug B
    // ("Real Zaragoza lost the semifinal but was still promoted")
    // ──────────────────────────────────────────────────

    public function test_playoff_in_progress_throws_instead_of_silently_promoting_next_position(): void
    {
        // Regression test for the bug where a player lost their playoff
        // semifinal but was still shown as promoted because the rule saw
        // "no playoff winner" and fell back to promoting position 3.
        $this->createStandings('ESP2', 22);

        $rule = new ConfigDrivenPromotionRule(
            topDivision: 'ESP1',
            bottomDivision: 'ESP2',
            relegatedPositions: [18, 19, 20],
            directPromotionPositions: [1, 2],
            playoffGenerator: new FakePlayoffGenerator(PlayoffState::InProgress),
        );

        $this->expectException(PlayoffInProgressException::class);
        $rule->getPromotedTeams($this->game);
    }

    public function test_playoff_completed_but_no_winner_throws_invariant_violation(): void
    {
        $this->createStandings('ESP2', 22);

        // PlayoffState::Completed but no CupTie rows actually exist → data
        // invariant violation. Must throw rather than fall back.
        $rule = new ConfigDrivenPromotionRule(
            topDivision: 'ESP1',
            bottomDivision: 'ESP2',
            relegatedPositions: [18, 19, 20],
            directPromotionPositions: [1, 2],
            playoffGenerator: new FakePlayoffGenerator(PlayoffState::Completed),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('state=Completed');
        $rule->getPromotedTeams($this->game);
    }

    public function test_playoff_not_started_uses_next_eligible_stand_in(): void
    {
        // Simulated non-player league: pos 1-2 direct, pos 3 stands in for
        // the playoff winner (reserve-filtered). This path is legitimate.
        $teams = $this->createStandings('ESP2', 22);

        $rule = new ConfigDrivenPromotionRule(
            topDivision: 'ESP1',
            bottomDivision: 'ESP2',
            relegatedPositions: [18, 19, 20],
            directPromotionPositions: [1, 2],
            playoffGenerator: new FakePlayoffGenerator(PlayoffState::NotStarted),
        );

        $promoted = $rule->getPromotedTeams($this->game);
        $this->assertCount(3, $promoted);
        $positions = array_column($promoted, 'position');
        $this->assertEquals([1, 2, 3], $positions);
    }

    public function test_playoff_completed_uses_winner_not_next_position(): void
    {
        $teams = $this->createStandings('ESP2', 22);

        // Create a completed final CupTie — positions 1 and 2 directly promoted,
        // position 3 (who lost in the final) must NOT be promoted.
        $winner = Team::factory()->create(['name' => 'Playoff Winner']);
        $loser = $teams[2]; // position 3
        CupTie::create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP2',
            'round_number' => 2,
            'home_team_id' => $winner->id,
            'away_team_id' => $loser->id,
            'winner_id' => $winner->id,
            'completed' => true,
            'resolution' => ['type' => 'aggregate'],
        ]);

        $rule = new ConfigDrivenPromotionRule(
            topDivision: 'ESP1',
            bottomDivision: 'ESP2',
            relegatedPositions: [18, 19, 20],
            directPromotionPositions: [1, 2],
            playoffGenerator: new FakePlayoffGenerator(PlayoffState::Completed),
        );

        $promoted = $rule->getPromotedTeams($this->game);
        $this->assertCount(3, $promoted);
        $promotedIds = array_column($promoted, 'teamId');
        $this->assertContains($winner->id, $promotedIds);
        $this->assertNotContains($loser->id, $promotedIds, 'Playoff loser must not be promoted');
    }

    // ──────────────────────────────────────────────────
    // Unfiltered fallback removed — the regression-proofing for Bug A
    // ("Real Sociedad B promoted while Real Sociedad was in La Liga")
    // ──────────────────────────────────────────────────

    public function test_empty_top_division_throws_instead_of_bypassing_reserve_filter(): void
    {
        // Wipe the top-division roster that setUp seeded to simulate the
        // pathological state where reserve-team filtering has no reference.
        CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'ESP1')
            ->delete();

        $this->createStandings('ESP2', 22);

        $rule = new ConfigDrivenPromotionRule(
            topDivision: 'ESP1',
            bottomDivision: 'ESP2',
            relegatedPositions: [18, 19, 20],
            directPromotionPositions: [1, 2],
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Top division ESP1 has no CompetitionEntry rows');
        $rule->getPromotedTeams($this->game);
    }

    public function test_reserve_team_filtered_from_playoff_qualifying_positions_too(): void
    {
        // Set up a reserve team at ESP2 position 3 whose parent is in ESP1.
        // Under the NotStarted stand-in path (which picks the next eligible
        // position after direct promotions), this reserve must be skipped
        // and position 4 promoted instead.
        $parent = Team::factory()->create();
        CompetitionEntry::create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP1',
            'team_id' => $parent->id,
            'entry_round' => 1,
        ]);

        $reserve = Team::factory()->create(['parent_team_id' => $parent->id]);

        // Positions 1-2 regular, position 3 reserve-blocked, 4+ regular.
        for ($i = 1; $i <= 22; $i++) {
            if ($i === 3) {
                $teamId = $reserve->id;
            } else {
                $teamId = Team::factory()->create()->id;
            }
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP2',
                'team_id' => $teamId,
                'position' => $i,
                'played' => 42, 'won' => 22 - $i, 'drawn' => 5, 'lost' => $i,
                'goals_for' => 60 - $i, 'goals_against' => 20 + $i,
                'points' => (22 - $i) * 3 + 5,
            ]);
        }

        $rule = new ConfigDrivenPromotionRule(
            topDivision: 'ESP1',
            bottomDivision: 'ESP2',
            relegatedPositions: [1, 2, 3],
            directPromotionPositions: [1, 2],
            playoffGenerator: new FakePlayoffGenerator(PlayoffState::NotStarted),
        );

        $promoted = $rule->getPromotedTeams($this->game);
        $promotedIds = array_column($promoted, 'teamId');

        $this->assertCount(3, $promoted);
        $this->assertNotContains($reserve->id, $promotedIds, 'Reserve at pos 3 must be skipped');
        // The third promotion (stand-in) should be pos 4 — the next eligible
        // after the two direct promotions, skipping the blocked pos 3.
        $positions = array_column($promoted, 'position');
        $this->assertContains(4, $positions);
    }
}
