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
use App\Modules\Competition\Playoffs\PrimeraRFEFPlayoffGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration tests for the Primera RFEF playoff generator and the
 * synthetic-league lane invariant. End-to-end promotion/relegation behaviour
 * for the planner lives in {@see PromotionRelegationProcessorTest}.
 */
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

    private function createCompletedSemifinals(array $groupATeams, array $groupBTeams, array $bracketAWinners = ['high', 'high'], array $bracketBWinners = ['high', 'high']): void
    {
        $bracketASemiTies = [
            [$groupBTeams[5], $groupATeams[2], PrimeraRFEFPlayoffGenerator::BRACKET_A, $bracketAWinners[0]],
            [$groupBTeams[4], $groupATeams[3], PrimeraRFEFPlayoffGenerator::BRACKET_A, $bracketAWinners[1]],
        ];
        $bracketBSemiTies = [
            [$groupATeams[5], $groupBTeams[2], PrimeraRFEFPlayoffGenerator::BRACKET_B, $bracketBWinners[0]],
            [$groupATeams[4], $groupBTeams[3], PrimeraRFEFPlayoffGenerator::BRACKET_B, $bracketBWinners[1]],
        ];

        foreach (array_merge($bracketASemiTies, $bracketBSemiTies) as [$home, $away, $bracket, $winnerSide]) {
            $winner = $winnerSide === 'low' ? $home : $away;
            CupTie::factory()
                ->forGame($this->game)
                ->inRound(1)
                ->between($home, $away)
                ->completed($winner, 'aggregate')
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

        $this->assertEquals($groupB[5]->id, $matchups[0][0]);
        $this->assertEquals($groupA[2]->id, $matchups[0][1]);
        $this->assertEquals(PrimeraRFEFPlayoffGenerator::BRACKET_A, $matchups[0][2]);

        $this->assertEquals($groupB[4]->id, $matchups[1][0]);
        $this->assertEquals($groupA[3]->id, $matchups[1][1]);
        $this->assertEquals(PrimeraRFEFPlayoffGenerator::BRACKET_A, $matchups[1][2]);

        $this->assertEquals($groupA[5]->id, $matchups[2][0]);
        $this->assertEquals($groupB[2]->id, $matchups[2][1]);
        $this->assertEquals(PrimeraRFEFPlayoffGenerator::BRACKET_B, $matchups[2][2]);

        $this->assertEquals($groupA[4]->id, $matchups[3][0]);
        $this->assertEquals($groupB[3]->id, $matchups[3][1]);
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
        $groupA = $this->createStandings('ESP3A', 20);
        $simulatedBTeams = $this->createSimulatedTeams(20);
        $this->createSimulatedSeason('ESP3B', $simulatedBTeams);

        $generator = new PrimeraRFEFPlayoffGenerator();
        $matchups = $generator->generateMatchups($this->game, 1);

        $this->assertCount(4, $matchups);

        $this->assertEquals($simulatedBTeams[4]->id, $matchups[0][0]);
        $this->assertEquals($groupA[2]->id, $matchups[0][1]);

        $this->assertEquals($simulatedBTeams[3]->id, $matchups[1][0]);
        $this->assertEquals($groupA[3]->id, $matchups[1][1]);
    }

    public function test_round_1_skips_reserve_team_and_slides_next_eligible(): void
    {
        $parentTeam = Team::factory()->create(['name' => 'Parent Club']);
        CompetitionEntry::create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP2',
            'team_id' => $parentTeam->id,
            'entry_round' => 1,
        ]);

        $reserveTeam = Team::factory()->create([
            'name' => 'Reserve B Team',
            'parent_team_id' => $parentTeam->id,
        ]);

        $groupA = $this->createStandings('ESP3A', 20, [2 => $reserveTeam]);
        $groupB = $this->createStandings('ESP3B', 20);

        $generator = new PrimeraRFEFPlayoffGenerator();
        $matchups = $generator->generateMatchups($this->game, 1);

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

        $bracketATeamIds = [$matchups[0][0], $matchups[0][1]];
        $this->assertContains($groupA[2]->id, $bracketATeamIds);
        $this->assertContains($groupA[3]->id, $bracketATeamIds);

        $bracketBTeamIds = [$matchups[1][0], $matchups[1][1]];
        $this->assertContains($groupB[2]->id, $bracketBTeamIds);
        $this->assertContains($groupB[3]->id, $bracketBTeamIds);
    }

    public function test_round_2_bracket_final_lower_finisher_hosts_first_leg_when_higher_seeds_win(): void
    {
        $groupA = $this->createStandings('ESP3A', 20);
        $groupB = $this->createStandings('ESP3B', 20);
        // Default: higher seeds win all semis. Bracket A final = A2 vs A3 → A3 hosts leg 1.
        $this->createCompletedSemifinals($groupA, $groupB);

        $generator = new PrimeraRFEFPlayoffGenerator();
        $matchups = $generator->generateMatchups($this->game, 2);

        $this->assertEquals($groupA[3]->id, $matchups[0][0], 'A3 (lower finisher) should host leg 1');
        $this->assertEquals($groupA[2]->id, $matchups[0][1]);
        $this->assertEquals($groupB[3]->id, $matchups[1][0], 'B3 (lower finisher) should host leg 1');
        $this->assertEquals($groupB[2]->id, $matchups[1][1]);
    }

    public function test_round_2_bracket_final_lower_finisher_hosts_first_leg_when_lower_seed_wins_one_semifinal(): void
    {
        $groupA = $this->createStandings('ESP3A', 20);
        $groupB = $this->createStandings('ESP3B', 20);
        // Bracket A: B5 beats A2 (low wins semi 1), A3 beats B4 (high wins semi 2)
        // Final: B5 vs A3 → A3 (pos 3) is higher finisher, B5 (pos 5) hosts leg 1.
        $this->createCompletedSemifinals($groupA, $groupB, ['low', 'high']);

        $generator = new PrimeraRFEFPlayoffGenerator();
        $matchups = $generator->generateMatchups($this->game, 2);

        $bracketAFinal = $matchups[0];
        $this->assertEquals($groupB[5]->id, $bracketAFinal[0], 'B5 (lower finisher) should host leg 1');
        $this->assertEquals($groupA[3]->id, $bracketAFinal[1]);
    }

    public function test_round_2_bracket_final_lower_finisher_hosts_first_leg_when_both_lower_seeds_win(): void
    {
        $groupA = $this->createStandings('ESP3A', 20);
        $groupB = $this->createStandings('ESP3B', 20);
        // Bracket A: B5 beats A2, B4 beats A3. Final: B5 vs B4 → B4 (pos 4) higher, B5 (pos 5) hosts leg 1.
        // Bracket B: A5 beats B2, A4 beats B3. Final: A5 vs A4 → A4 higher, A5 hosts leg 1.
        $this->createCompletedSemifinals($groupA, $groupB, ['low', 'low'], ['low', 'low']);

        $generator = new PrimeraRFEFPlayoffGenerator();
        $matchups = $generator->generateMatchups($this->game, 2);

        $this->assertEquals($groupB[5]->id, $matchups[0][0], 'B5 should host bracket A leg 1');
        $this->assertEquals($groupB[4]->id, $matchups[0][1]);
        $this->assertEquals($groupA[5]->id, $matchups[1][0], 'A5 should host bracket B leg 1');
        $this->assertEquals($groupA[4]->id, $matchups[1][1]);
    }

    public function test_round_2_bracket_final_uses_simulated_standings_for_sister_group(): void
    {
        $groupA = $this->createStandings('ESP3A', 20);
        $simulatedBTeams = $this->createSimulatedTeams(20);
        $this->createSimulatedSeason('ESP3B', $simulatedBTeams);

        // Build group B map by 1-indexed position to mirror createStandings' shape.
        $groupB = [];
        foreach ($simulatedBTeams as $index => $team) {
            $groupB[$index + 1] = $team;
        }

        $this->createCompletedSemifinals($groupA, $groupB);

        $generator = new PrimeraRFEFPlayoffGenerator();
        $matchups = $generator->generateMatchups($this->game, 2);

        // Bracket B final = B2 vs B3 (both from simulated standings). Position
        // lookup must hit the SimulatedSeason fallback and pick B3 as host.
        $this->assertEquals($groupB[3]->id, $matchups[1][0], 'B3 (sim pos 3) should host leg 1 against B2');
        $this->assertEquals($groupB[2]->id, $matchups[1][1]);
    }

    // ──────────────────────────────────────────────────
    // Playoff Generator: isComplete + state
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

    // ──────────────────────────────────────────────────
    // Lane invariant: simulated and standings cannot coexist
    // ──────────────────────────────────────────────────

    public function test_resolver_refuses_to_write_standings_after_simulated_lane_committed(): void
    {
        $this->game->update(['competition_id' => 'ESP2']);

        $simulatedA = $this->createSimulatedTeams(20);
        $simulatedB = $this->createSimulatedTeams(20);
        $this->createSimulatedSeason('ESP3A', $simulatedA);
        $this->createSimulatedSeason('ESP3B', $simulatedB);

        $resolver = app(\App\Modules\Match\Services\SyntheticLeagueResolver::class);
        $esp3a = Competition::find('ESP3A');

        $resolver->catchUp($this->game, $esp3a, $this->game->current_date?->copy()->addYear());

        $this->assertSame(0, GameStanding::where('game_id', $this->game->id)
            ->where('competition_id', 'ESP3A')
            ->count(), 'catchUp must not write game_standings when SimulatedSeason already exists.');
        $this->assertSame(0, GameMatch::where('game_id', $this->game->id)
            ->where('competition_id', 'ESP3A')
            ->count(), 'catchUp must not write game_matches when SimulatedSeason already exists.');
    }

    public function test_resolver_initializes_when_no_simulated_season_exists(): void
    {
        $this->game->update(['competition_id' => 'ESP2']);

        $teams = $this->createSimulatedTeams(20);
        foreach ($teams as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP3A',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        $resolver = app(\App\Modules\Match\Services\SyntheticLeagueResolver::class);
        $esp3a = Competition::find('ESP3A');

        $resolver->ensureInitialized($this->game, $esp3a);

        $this->assertGreaterThan(0, GameMatch::where('game_id', $this->game->id)
            ->where('competition_id', 'ESP3A')
            ->count(), 'ensureInitialized should create fixtures when no lane is locked yet.');
        $this->assertGreaterThan(0, GameStanding::where('game_id', $this->game->id)
            ->where('competition_id', 'ESP3A')
            ->count(), 'ensureInitialized should seed standings when no lane is locked yet.');
        $this->assertSame(0, SimulatedSeason::where('game_id', $this->game->id)
            ->where('competition_id', 'ESP3A')
            ->where('season', $this->game->season)
            ->count(), 'No SimulatedSeason should be written by the resolver itself.');
    }
}
