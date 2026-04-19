<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Exceptions\PlayoffInProgressException;
use App\Modules\Competition\Playoffs\PrimeraRFEFPlayoffGenerator;
use App\Modules\Competition\Promotions\PrimeraRFEFPromotionRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrimeraRFEFPromotionTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create([
            'id' => 'ESP2', 'tier' => 2, 'handler_type' => 'league_with_playoff',
        ]);
        Competition::factory()->league()->create([
            'id' => 'ESP3A', 'tier' => 3, 'handler_type' => 'league_with_playoff',
        ]);
        Competition::factory()->league()->create([
            'id' => 'ESP3B', 'tier' => 3, 'handler_type' => 'league_with_playoff',
        ]);
        Competition::factory()->knockoutCup()->create([
            'id' => 'ESP3PO', 'tier' => 3,
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create();
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP3A',
            'season' => '2025',
        ]);
    }

    // ──────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────

    private function createStandings(string $competitionId, int $count, array $preAssigned = []): array
    {
        $teams = [];
        for ($i = 1; $i <= $count; $i++) {
            $team = $preAssigned[$i] ?? Team::factory()->create();
            $teams[$i] = $team;

            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => $competitionId,
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);

            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => $competitionId,
                'team_id' => $team->id,
                'position' => $i,
                'played' => ($count - 1) * 2,
                'won' => max(0, $count - $i),
                'drawn' => 3,
                'lost' => max(0, $i - 1),
                'goals_for' => max(10, 60 - $i * 2),
                'goals_against' => 20 + $i,
                'points' => max(0, $count - $i) * 3 + 3,
            ]);
        }
        return $teams;
    }

    private function createSimulatedSeason(string $competitionId, array $teams): void
    {
        $teamIds = [];
        foreach ($teams as $team) {
            $teamIds[] = $team->id;

            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => $competitionId,
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        SimulatedSeason::create([
            'game_id' => $this->game->id,
            'season' => '2025',
            'competition_id' => $competitionId,
            'results' => $teamIds,
        ]);
    }

    private function createSimulatedTeams(int $count): array
    {
        $teams = [];
        for ($i = 0; $i < $count; $i++) {
            $teams[] = Team::factory()->create();
        }
        return $teams;
    }

    private function createCompletedSemifinals(array $groupATeams, array $groupBTeams): void
    {
        // Bracket A: A5 vs A2, B4 vs B3
        // Bracket B: B5 vs B2, A4 vs A3
        // Index: 2nd=pos2, 3rd=pos3, 4th=pos4, 5th=pos5

        $bracketASemiTies = [
            [$groupATeams[5], $groupATeams[2], PrimeraRFEFPlayoffGenerator::BRACKET_A],
            [$groupBTeams[4], $groupBTeams[3], PrimeraRFEFPlayoffGenerator::BRACKET_A],
        ];
        $bracketBSemiTies = [
            [$groupBTeams[5], $groupBTeams[2], PrimeraRFEFPlayoffGenerator::BRACKET_B],
            [$groupATeams[4], $groupATeams[3], PrimeraRFEFPlayoffGenerator::BRACKET_B],
        ];

        foreach (array_merge($bracketASemiTies, $bracketBSemiTies) as [$home, $away, $bracket]) {
            CupTie::factory()
                ->forGame($this->game)
                ->inRound(1)
                ->between($home, $away)
                ->completed($away, 'aggregate')
                ->create([
                    'competition_id' => 'ESP3PO',
                    'bracket_position' => $bracket,
                ]);
        }
    }

    private function createCompletedFinals(Team $bracketAWinner, Team $bracketALoser, Team $bracketBWinner, Team $bracketBLoser): void
    {
        CupTie::factory()
            ->forGame($this->game)
            ->inRound(2)
            ->between($bracketAWinner, $bracketALoser)
            ->completed($bracketAWinner, 'aggregate')
            ->create([
                'competition_id' => 'ESP3PO',
                'bracket_position' => PrimeraRFEFPlayoffGenerator::BRACKET_A,
            ]);

        CupTie::factory()
            ->forGame($this->game)
            ->inRound(2)
            ->between($bracketBWinner, $bracketBLoser)
            ->completed($bracketBWinner, 'aggregate')
            ->create([
                'competition_id' => 'ESP3PO',
                'bracket_position' => PrimeraRFEFPlayoffGenerator::BRACKET_B,
            ]);
    }

    // ──────────────────────────────────────────────────
    // Playoff Generator: Round 1 (Semifinals)
    // ──────────────────────────────────────────────────

    public function test_round_1_generates_4_matchups_with_correct_brackets(): void
    {
        $groupA = $this->createStandings('ESP3A', 20);
        $groupB = $this->createStandings('ESP3B', 20);

        $generator = new PrimeraRFEFPlayoffGenerator();
        $matchups = $generator->generateMatchups($this->game, 1);

        $this->assertCount(4, $matchups);

        // Bracket A: [A5, A2, 1] and [B4, B3, 1]
        $this->assertEquals($groupA[5]->id, $matchups[0][0]);
        $this->assertEquals($groupA[2]->id, $matchups[0][1]);
        $this->assertEquals(PrimeraRFEFPlayoffGenerator::BRACKET_A, $matchups[0][2]);

        $this->assertEquals($groupB[4]->id, $matchups[1][0]);
        $this->assertEquals($groupB[3]->id, $matchups[1][1]);
        $this->assertEquals(PrimeraRFEFPlayoffGenerator::BRACKET_A, $matchups[1][2]);

        // Bracket B: [B5, B2, 2] and [A4, A3, 2]
        $this->assertEquals($groupB[5]->id, $matchups[2][0]);
        $this->assertEquals($groupB[2]->id, $matchups[2][1]);
        $this->assertEquals(PrimeraRFEFPlayoffGenerator::BRACKET_B, $matchups[2][2]);

        $this->assertEquals($groupA[4]->id, $matchups[3][0]);
        $this->assertEquals($groupA[3]->id, $matchups[3][1]);
        $this->assertEquals(PrimeraRFEFPlayoffGenerator::BRACKET_B, $matchups[3][2]);
    }

    public function test_round_1_populates_esp3po_competition_entries(): void
    {
        $this->createStandings('ESP3A', 20);
        $this->createStandings('ESP3B', 20);

        $generator = new PrimeraRFEFPlayoffGenerator();
        $generator->generateMatchups($this->game, 1);

        $entries = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'ESP3PO')
            ->count();

        $this->assertEquals(8, $entries);
    }

    public function test_round_1_uses_simulated_standings_for_sister_group(): void
    {
        // Player in ESP3A → real standings; ESP3B → simulated
        $groupA = $this->createStandings('ESP3A', 20);
        $simulatedBTeams = $this->createSimulatedTeams(20);
        $this->createSimulatedSeason('ESP3B', $simulatedBTeams);

        $generator = new PrimeraRFEFPlayoffGenerator();
        $matchups = $generator->generateMatchups($this->game, 1);

        $this->assertCount(4, $matchups);

        // Group A positions should come from real standings
        $this->assertEquals($groupA[5]->id, $matchups[0][0]); // A5
        $this->assertEquals($groupA[2]->id, $matchups[0][1]); // A2

        // Group B positions should come from simulated results (index-based).
        // matchups[1] is the [B4, B3] tie from Bracket A.
        $this->assertEquals($simulatedBTeams[3]->id, $matchups[1][0]); // B4 (index 3)
        $this->assertEquals($simulatedBTeams[2]->id, $matchups[1][1]); // B3 (index 2)
    }

    public function test_round_1_skips_reserve_team_and_slides_next_eligible(): void
    {
        // Create a parent team in ESP2
        $parentTeam = Team::factory()->create(['name' => 'Parent Club']);
        CompetitionEntry::create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP2',
            'team_id' => $parentTeam->id,
            'entry_round' => 1,
        ]);

        // Position 2 of Group A is a reserve team whose parent is in ESP2
        $reserveTeam = Team::factory()->create([
            'name' => 'Reserve B Team',
            'parent_team_id' => $parentTeam->id,
        ]);

        $groupA = $this->createStandings('ESP3A', 20, [2 => $reserveTeam]);
        $groupB = $this->createStandings('ESP3B', 20);

        $generator = new PrimeraRFEFPlayoffGenerator();
        $matchups = $generator->generateMatchups($this->game, 1);

        // The reserve at position 2 is blocked. Position 6 slides into the
        // 4th playoff slot, so the qualifying set becomes [3, 4, 5, 6].
        $allTeamIds = collect($matchups)->flatMap(fn ($m) => [$m[0], $m[1]])->toArray();
        $this->assertNotContains($reserveTeam->id, $allTeamIds);
        $this->assertContains($groupA[6]->id, $allTeamIds);
    }

    // ──────────────────────────────────────────────────
    // Playoff Generator: Round 2 (Bracket Finals)
    // ──────────────────────────────────────────────────

    public function test_round_2_pairs_semifinal_winners_within_brackets(): void
    {
        $groupA = $this->createStandings('ESP3A', 20);
        $groupB = $this->createStandings('ESP3B', 20);
        $this->createCompletedSemifinals($groupA, $groupB);

        $generator = new PrimeraRFEFPlayoffGenerator();
        $matchups = $generator->generateMatchups($this->game, 2);

        $this->assertCount(2, $matchups);
        $this->assertEquals(PrimeraRFEFPlayoffGenerator::BRACKET_A, $matchups[0][2]);
        $this->assertEquals(PrimeraRFEFPlayoffGenerator::BRACKET_B, $matchups[1][2]);

        // Bracket A winners: A2 (beat A5) and B3 (beat B4)
        $bracketATeamIds = [$matchups[0][0], $matchups[0][1]];
        $this->assertContains($groupA[2]->id, $bracketATeamIds);
        $this->assertContains($groupB[3]->id, $bracketATeamIds);

        // Bracket B winners: B2 (beat B5) and A3 (beat A4)
        $bracketBTeamIds = [$matchups[1][0], $matchups[1][1]];
        $this->assertContains($groupB[2]->id, $bracketBTeamIds);
        $this->assertContains($groupA[3]->id, $bracketBTeamIds);
    }

    // ──────────────────────────────────────────────────
    // Playoff Generator: isComplete
    // ──────────────────────────────────────────────────

    public function test_is_complete_false_when_no_finals_exist(): void
    {
        $groupA = $this->createStandings('ESP3A', 20);
        $groupB = $this->createStandings('ESP3B', 20);
        $this->createCompletedSemifinals($groupA, $groupB);

        $generator = new PrimeraRFEFPlayoffGenerator();
        $this->assertFalse($generator->isComplete($this->game));
    }

    public function test_is_complete_true_when_both_bracket_finals_completed(): void
    {
        $groupA = $this->createStandings('ESP3A', 20);
        $groupB = $this->createStandings('ESP3B', 20);
        $this->createCompletedSemifinals($groupA, $groupB);
        $this->createCompletedFinals($groupA[2], $groupB[3], $groupB[2], $groupA[3]);

        $generator = new PrimeraRFEFPlayoffGenerator();
        $this->assertTrue($generator->isComplete($this->game));
    }

    // ──────────────────────────────────────────────────
    // Promotion Rule: getPromotedTeams
    // ──────────────────────────────────────────────────

    public function test_get_promoted_teams_returns_4_with_correct_origins(): void
    {
        $groupA = $this->createStandings('ESP3A', 20);
        $groupB = $this->createStandings('ESP3B', 20);
        $this->createStandings('ESP2', 22);

        // Completed playoff: A2 wins bracket A, B2 wins bracket B
        $this->createCompletedSemifinals($groupA, $groupB);
        $this->createCompletedFinals($groupA[2], $groupB[3], $groupB[2], $groupA[3]);

        $rule = new PrimeraRFEFPromotionRule();
        $promoted = $rule->getPromotedTeams($this->game);

        $this->assertCount(4, $promoted);

        $origins = collect($promoted)->pluck('origin')->toArray();
        $teamIds = collect($promoted)->pluck('teamId')->toArray();

        // Direct promotions: group winners
        $this->assertContains($groupA[1]->id, $teamIds);
        $this->assertContains($groupB[1]->id, $teamIds);

        // Each promoted team has an origin tag
        $this->assertContains('ESP3A', $origins);
        $this->assertContains('ESP3B', $origins);
    }

    public function test_get_promoted_teams_uses_simulated_fallback_when_no_playoff(): void
    {
        // Both groups simulated (player not in ESP3 at all, e.g. in ESP2)
        $this->game->update(['competition_id' => 'ESP2']);

        $simulatedATeams = $this->createSimulatedTeams(20);
        $this->createSimulatedSeason('ESP3A', $simulatedATeams);

        $simulatedBTeams = $this->createSimulatedTeams(20);
        $this->createSimulatedSeason('ESP3B', $simulatedBTeams);

        $this->createStandings('ESP2', 22);

        $rule = new PrimeraRFEFPromotionRule();
        $promoted = $rule->getPromotedTeams($this->game);

        $this->assertCount(4, $promoted);

        // Direct: simulated position 1 from each group
        $this->assertContains($simulatedATeams[0]->id, collect($promoted)->pluck('teamId')->toArray());
        $this->assertContains($simulatedBTeams[0]->id, collect($promoted)->pluck('teamId')->toArray());

        // Playoff stand-ins: position 2 from each group's simulation
        $this->assertContains($simulatedATeams[1]->id, collect($promoted)->pluck('teamId')->toArray());
        $this->assertContains($simulatedBTeams[1]->id, collect($promoted)->pluck('teamId')->toArray());
    }

    // ──────────────────────────────────────────────────
    // Promotion Rule: getRelegatedTeams
    // ──────────────────────────────────────────────────

    public function test_get_relegated_teams_returns_4_from_esp2(): void
    {
        $esp2Teams = $this->createStandings('ESP2', 22);

        // Rule is a no-op for games with no ESP3 activity; seed a single
        // ESP3A entry to satisfy isActiveForGame so relegation logic runs.
        CompetitionEntry::create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP3A',
            'team_id' => Team::factory()->create()->id,
            'entry_round' => 1,
        ]);

        $rule = new PrimeraRFEFPromotionRule();
        $relegated = $rule->getRelegatedTeams($this->game);

        $this->assertCount(4, $relegated);

        $teamIds = collect($relegated)->pluck('teamId')->toArray();
        $this->assertContains($esp2Teams[19]->id, $teamIds);
        $this->assertContains($esp2Teams[20]->id, $teamIds);
        $this->assertContains($esp2Teams[21]->id, $teamIds);
        $this->assertContains($esp2Teams[22]->id, $teamIds);
    }

    // ──────────────────────────────────────────────────
    // Promotion Rule: performSwap
    // ──────────────────────────────────────────────────

    public function test_perform_swap_balanced_redistribution(): void
    {
        $groupA = $this->createStandings('ESP3A', 20);
        $groupB = $this->createStandings('ESP3B', 20);
        $esp2 = $this->createStandings('ESP2', 22);
        $this->createCompletedSemifinals($groupA, $groupB);
        $this->createCompletedFinals($groupA[2], $groupB[3], $groupB[2], $groupA[3]);

        $rule = new PrimeraRFEFPromotionRule();
        $promoted = $rule->getPromotedTeams($this->game);
        $relegated = $rule->getRelegatedTeams($this->game);

        $rule->performSwap($this->game, $promoted, $relegated);

        // Both groups should return to 20 teams
        $this->assertGroupSize('ESP3A', 20);
        $this->assertGroupSize('ESP3B', 20);

        // ESP2 should still have 22 teams
        $this->assertGroupSize('ESP2', 22);
    }

    public function test_perform_swap_uneven_redistribution(): void
    {
        // Both bracket winners from Group A → 3 leave A, 1 leaves B
        $groupA = $this->createStandings('ESP3A', 20);
        $groupB = $this->createStandings('ESP3B', 20);
        $esp2 = $this->createStandings('ESP2', 22);
        $this->createCompletedSemifinals($groupA, $groupB);

        // Both bracket winners are Group A teams
        $this->createCompletedFinals($groupA[2], $groupB[3], $groupA[3], $groupB[2]);

        $rule = new PrimeraRFEFPromotionRule();
        $promoted = $rule->getPromotedTeams($this->game);
        $relegated = $rule->getRelegatedTeams($this->game);

        // Verify origins: 3 from A (pos 1 + 2 bracket winners), 1 from B (pos 1)
        $fromA = collect($promoted)->where('origin', 'ESP3A')->count();
        $fromB = collect($promoted)->where('origin', 'ESP3B')->count();
        $this->assertEquals(3, $fromA);
        $this->assertEquals(1, $fromB);

        $rule->performSwap($this->game, $promoted, $relegated);

        // Despite uneven departures, both groups return to 20
        $this->assertGroupSize('ESP3A', 20);
        $this->assertGroupSize('ESP3B', 20);
        $this->assertGroupSize('ESP2', 22);
    }

    public function test_perform_swap_clears_esp3po_state(): void
    {
        $groupA = $this->createStandings('ESP3A', 20);
        $groupB = $this->createStandings('ESP3B', 20);
        $this->createStandings('ESP2', 22);
        $this->createCompletedSemifinals($groupA, $groupB);
        $this->createCompletedFinals($groupA[2], $groupB[3], $groupB[2], $groupA[3]);

        // Manually create some ESP3PO entries to verify cleanup
        CompetitionEntry::create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP3PO',
            'team_id' => $groupA[2]->id,
            'entry_round' => 1,
        ]);

        $rule = new PrimeraRFEFPromotionRule();
        $promoted = $rule->getPromotedTeams($this->game);
        $relegated = $rule->getRelegatedTeams($this->game);
        $rule->performSwap($this->game, $promoted, $relegated);

        $this->assertEquals(0, CupTie::where('game_id', $this->game->id)->where('competition_id', 'ESP3PO')->count());
        $this->assertEquals(0, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP3PO')->count());
        $this->assertEquals(0, GameStanding::where('game_id', $this->game->id)->where('competition_id', 'ESP3PO')->count());
    }

    public function test_perform_swap_updates_player_competition_when_promoted(): void
    {
        // Player's team is at position 1 of ESP3A (direct promotion)
        $playerTeam = $this->game->team;
        $groupA = $this->createStandings('ESP3A', 20, [1 => $playerTeam]);
        $groupB = $this->createStandings('ESP3B', 20);
        $this->createStandings('ESP2', 22);
        $this->createCompletedSemifinals($groupA, $groupB);
        $this->createCompletedFinals($groupA[2], $groupB[3], $groupB[2], $groupA[3]);

        $rule = new PrimeraRFEFPromotionRule();
        $promoted = $rule->getPromotedTeams($this->game);
        $relegated = $rule->getRelegatedTeams($this->game);
        $rule->performSwap($this->game, $promoted, $relegated);

        $this->game->refresh();
        $this->assertEquals('ESP2', $this->game->competition_id);
    }

    // ──────────────────────────────────────────────────
    // Assertion helpers
    // ──────────────────────────────────────────────────

    private function assertGroupSize(string $competitionId, int $expected): void
    {
        $actual = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', $competitionId)
            ->count();

        $this->assertEquals(
            $expected,
            $actual,
            "{$competitionId} should have {$expected} teams, got {$actual}"
        );
    }

    // ──────────────────────────────────────────────────
    // Playoff state machine — regression-proofing
    // ──────────────────────────────────────────────────

    public function test_playoff_generator_reports_not_started_when_no_cup_ties(): void
    {
        $this->createStandings('ESP3A', 20);
        $this->createStandings('ESP3B', 20);

        $generator = new PrimeraRFEFPlayoffGenerator();
        $this->assertEquals(PlayoffState::NotStarted, $generator->state($this->game));
    }

    public function test_playoff_generator_reports_in_progress_while_finals_unresolved(): void
    {
        $groupA = $this->createStandings('ESP3A', 20);
        $groupB = $this->createStandings('ESP3B', 20);
        $this->createCompletedSemifinals($groupA, $groupB);
        // No round-2 finals created.

        $generator = new PrimeraRFEFPlayoffGenerator();
        $this->assertEquals(PlayoffState::InProgress, $generator->state($this->game));
    }

    public function test_playoff_generator_reports_completed_when_both_finals_resolved(): void
    {
        $groupA = $this->createStandings('ESP3A', 20);
        $groupB = $this->createStandings('ESP3B', 20);
        $this->createCompletedSemifinals($groupA, $groupB);
        $this->createCompletedFinals($groupA[2], $groupB[3], $groupB[2], $groupA[3]);

        $generator = new PrimeraRFEFPlayoffGenerator();
        $this->assertEquals(PlayoffState::Completed, $generator->state($this->game));
    }

    public function test_get_promoted_teams_throws_when_playoff_in_progress(): void
    {
        // Regression test for the Primera RFEF equivalent of Bug B: the
        // closing pipeline firing while semifinals are done but finals are
        // not. Must throw — not fall back to simulated stand-ins that would
        // silently promote losing teams.
        $groupA = $this->createStandings('ESP3A', 20);
        $groupB = $this->createStandings('ESP3B', 20);
        $this->createStandings('ESP2', 22);
        $this->createCompletedSemifinals($groupA, $groupB);
        // Intentionally NO round-2 finals.

        $rule = new PrimeraRFEFPromotionRule();

        $this->expectException(PlayoffInProgressException::class);
        $rule->getPromotedTeams($this->game);
    }

    public function test_get_promoted_teams_throws_when_finals_exist_but_not_all_resolved(): void
    {
        // Only one of two bracket finals completed. Since isComplete() returns
        // false but CupTies exist, state is InProgress → throw.
        $groupA = $this->createStandings('ESP3A', 20);
        $groupB = $this->createStandings('ESP3B', 20);
        $this->createStandings('ESP2', 22);
        $this->createCompletedSemifinals($groupA, $groupB);

        // Only bracket A's final completed; bracket B's never created.
        CupTie::factory()
            ->forGame($this->game)
            ->inRound(2)
            ->between($groupA[2], $groupB[3])
            ->completed($groupA[2], 'aggregate')
            ->create([
                'competition_id' => 'ESP3PO',
                'bracket_position' => PrimeraRFEFPlayoffGenerator::BRACKET_A,
            ]);

        $rule = new PrimeraRFEFPromotionRule();
        $this->expectException(PlayoffInProgressException::class);
        $rule->getPromotedTeams($this->game);
    }
}
