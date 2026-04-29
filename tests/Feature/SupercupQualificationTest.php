<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\SupercupQualificationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies Spanish Supercopa qualification follows the RFEF rule:
 *
 *   "Si el campeón o subcampeón de Copa del Rey ya está clasificado entre
 *    los dos primeros de Liga, la plaza de la Supercopa se otorga al 3º
 *    (y 4º si fuera necesario) clasificado de la Liga para completar los
 *    cuatro participantes."
 *
 * Each test pins one row of the (cup-winner ∈ top2, cup-runnerup ∈ top2)
 * truth table so future edits can't silently regress to the pre-fix
 * cup-first ordering or the partial-output bug behind Population A.
 */
class SupercupQualificationTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    /** @var Team[] keyed by league position (1 = champion). */
    private array $league = [];

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);
        Competition::factory()->knockoutCup()->create(['id' => 'ESPCUP', 'country' => 'ES']);
        Competition::factory()->knockoutCup()->create(['id' => 'ESPSUP', 'country' => 'ES']);

        $user = User::factory()->create();
        $userTeam = Team::factory()->create(['country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
        ]);

        // 6 ranked league teams. Position 1 is the user's team to keep
        // the shape consistent with how SetupNewGame seeds things.
        $this->league[1] = $userTeam;
        for ($pos = 2; $pos <= 6; $pos++) {
            $this->league[$pos] = Team::factory()->create(['country' => 'ES', 'name' => "League #{$pos}"]);
        }

        foreach ($this->league as $position => $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP1',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP1',
                'team_id' => $team->id,
                'position' => $position,
                'played' => 38,
                'won' => max(0, 25 - $position),
                'drawn' => 5,
                'lost' => max(0, $position - 5),
                'goals_for' => 60 - $position,
                'goals_against' => 20 + $position,
                'points' => max(0, (25 - $position) * 3 + 5),
            ]);
        }
    }

    public function test_no_overlap_qualifies_both_cup_finalists_plus_league_top_2(): void
    {
        // Cup winner = pos 5, cup runner-up = pos 6. Neither in league top 2.
        // Priority order: cup winner, cup runner-up, league 1st, league 2nd.
        $this->setCupFinalists($this->league[5], $this->league[6]);

        $this->runProcessor();

        $this->assertSupercupTeams([
            $this->league[5]->id, // cup winner
            $this->league[6]->id, // cup runner-up
            $this->league[1]->id, // league champion
            $this->league[2]->id, // league runner-up
        ]);
    }

    public function test_cup_winner_is_league_champion_advances_league_spot_to_third(): void
    {
        // Cup winner = league 1st. The cup-finalist slot is preserved; the
        // league's "1st" slot is what advances down to 3rd.
        $this->setCupFinalists($this->league[1], $this->league[5]);

        $this->runProcessor();

        $this->assertSupercupTeams([
            $this->league[1]->id, // cup winner (= league 1st, but holds the CUP slot)
            $this->league[5]->id, // cup runner-up
            $this->league[2]->id, // league runner-up
            $this->league[3]->id, // 3rd fills the displaced league-champion slot
        ]);
    }

    public function test_cup_runnerup_is_league_runnerup_advances_league_spot_to_third(): void
    {
        // Cup runner-up = league 2nd. The cup-finalist slot is preserved;
        // the league's "2nd" slot is what advances down to 3rd.
        $this->setCupFinalists($this->league[5], $this->league[2]);

        $this->runProcessor();

        $this->assertSupercupTeams([
            $this->league[5]->id, // cup winner
            $this->league[2]->id, // cup runner-up (= league 2nd, holds the CUP slot)
            $this->league[1]->id, // league champion
            $this->league[3]->id, // 3rd fills the displaced league-runner-up slot
        ]);
    }

    public function test_both_cup_finalists_in_top_2_advances_league_to_third_and_fourth(): void
    {
        // Both cup finalists are league 1st and 2nd. Both league slots
        // advance, so 3rd AND 4th fill them.
        $this->setCupFinalists($this->league[1], $this->league[2]);

        $this->runProcessor();

        $this->assertSupercupTeams([
            $this->league[1]->id, // cup winner (= league 1st)
            $this->league[2]->id, // cup runner-up (= league 2nd)
            $this->league[3]->id, // 3rd fills first displaced league slot
            $this->league[4]->id, // 4th fills second displaced league slot
        ]);
    }

    public function test_swapped_finalists_in_top_2_still_advances_to_third_and_fourth(): void
    {
        // Order-swap: cup winner = league 2nd, cup runner-up = league 1st.
        // Same set, with cup-priority ordering preserved.
        $this->setCupFinalists($this->league[2], $this->league[1]);

        $this->runProcessor();

        $this->assertSupercupTeams([
            $this->league[2]->id, // cup winner
            $this->league[1]->id, // cup runner-up
            $this->league[3]->id, // 3rd fills displaced slot
            $this->league[4]->id, // 4th fills displaced slot
        ]);
    }

    public function test_cup_winner_third_and_runnerup_outside_top_2_no_advance(): void
    {
        // Cup winner = league 3rd — NOT in top 2, so no league spot is
        // displaced. League 1st/2nd qualify in their normal slots.
        $this->setCupFinalists($this->league[3], $this->league[6]);

        $this->runProcessor();

        $this->assertSupercupTeams([
            $this->league[3]->id, // cup winner
            $this->league[6]->id, // cup runner-up
            $this->league[1]->id, // league champion
            $this->league[2]->id, // league runner-up
        ]);
    }

    public function test_no_cup_run_falls_back_to_league_top_4(): void
    {
        // Cup didn't run at all (no completed final). The four supercup
        // slots fall to league positions 1–4.
        $this->runProcessor();

        $this->assertSupercupTeams([
            $this->league[1]->id,
            $this->league[2]->id,
            $this->league[3]->id,
            $this->league[4]->id,
        ]);
    }

    public function test_throws_when_cannot_produce_4_qualifiers(): void
    {
        // Cup didn't run AND the league has fewer than 4 standings rows —
        // determineQualifiers can only produce 2, which is the Population A
        // signature (Atlético + Real Valladolid in production). Surface it
        // loudly so the upstream cause shows up immediately instead of
        // creating a half-populated supercup.
        GameStanding::where('game_id', $this->game->id)
            ->where('competition_id', 'ESP1')
            ->whereIn('position', [3, 4, 5, 6])
            ->delete();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/SupercupQualification.*expected 4 qualifiers.*got 2/i');

        $this->runProcessor();
    }

    public function test_qualifiers_are_persisted_to_competition_entries(): void
    {
        $this->setCupFinalists($this->league[5], $this->league[6]);

        $this->runProcessor();

        $entries = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'ESPSUP')
            ->pluck('team_id')
            ->all();

        $this->assertCount(4, $entries);
        $this->assertContains($this->league[1]->id, $entries);
        $this->assertContains($this->league[2]->id, $entries);
        $this->assertContains($this->league[5]->id, $entries);
        $this->assertContains($this->league[6]->id, $entries);
    }

    public function test_rerun_replaces_previous_supercup_entries(): void
    {
        // Stale entry from a prior run — must be wiped, not merged.
        $stranger = Team::factory()->create(['country' => 'ES']);
        CompetitionEntry::create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESPSUP',
            'team_id' => $stranger->id,
            'entry_round' => 1,
        ]);

        $this->setCupFinalists($this->league[5], $this->league[6]);
        $this->runProcessor();

        $entries = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'ESPSUP')
            ->pluck('team_id')
            ->all();

        $this->assertNotContains($stranger->id, $entries);
        $this->assertCount(4, $entries);
    }

    public function test_metadata_records_qualifiers_in_priority_order(): void
    {
        // Cup winner = league champion → league spot advances to 3rd.
        $this->setCupFinalists($this->league[1], $this->league[5]);

        $data = $this->runProcessor();

        $this->assertSame(
            [
                $this->league[1]->id, // cup winner
                $this->league[5]->id, // cup runner-up
                $this->league[2]->id, // league runner-up
                $this->league[3]->id, // 3rd fills displaced league-champion slot
            ],
            $data->getMetadata('supercupQualifiers'),
        );
    }

    private function setCupFinalists(Team $winner, Team $runnerUp): void
    {
        // Mimic the structure SupercupQualificationProcessor reads: the
        // single completed cup tie at round = cup_final_round (7 for ESP).
        CupTie::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'game_id' => $this->game->id,
            'competition_id' => 'ESPCUP',
            'round_number' => 7,
            'home_team_id' => $winner->id,
            'away_team_id' => $runnerUp->id,
            'winner_id' => $winner->id,
            'completed' => true,
        ]);
    }

    private function runProcessor(): SeasonTransitionData
    {
        $data = new SeasonTransitionData(oldSeason: '2025', newSeason: '2026', competitionId: 'ESP1');
        app(SupercupQualificationProcessor::class)->process($this->game, $data);
        return $data;
    }

    /**
     * Assert the metadata-recorded qualifier list matches the expected order
     * exactly. RFEF priority ordering is part of the contract, not just the
     * resulting set — see the docblock on determineQualifiers.
     *
     * @param  string[]  $expected
     */
    private function assertSupercupTeams(array $expected): void
    {
        $entries = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'ESPSUP')
            ->pluck('team_id')
            ->all();

        $this->assertCount(
            count($expected),
            $entries,
            'Supercup must always have exactly the expected number of qualifiers',
        );
        $this->assertEqualsCanonicalizing(
            $expected,
            $entries,
            'Supercup qualifier set mismatch',
        );
    }
}
