<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Services\SwissKnockoutGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SwissKnockoutGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private SwissKnockoutGenerator $generator;
    private Game $game;
    private Competition $competition;

    /** @var array<int, Team> league position (1..36) => Team */
    private array $teamsByPosition = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(SwissKnockoutGenerator::class);

        $this->competition = Competition::factory()->create([
            'id' => 'UCL',
            'handler_type' => 'swiss_format',
            'season' => '2025',
        ]);

        $user = User::factory()->create();
        $userTeam = Team::factory()->create();

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'season' => '2025',
        ]);

        $this->seedStandings();
    }

    public function test_playoff_matchups_carry_bracket_index(): void
    {
        $matchups = $this->generator->generateMatchups($this->game, $this->competition->id, 1);

        $this->assertCount(8, $matchups);

        $bracketCounts = [0 => 0, 1 => 0, 2 => 0, 3 => 0];
        foreach ($matchups as $matchup) {
            [$home, $away, $bracket] = $matchup;
            $this->assertNotNull($bracket, 'Playoff matchups must include a bracket index');
            $this->assertContains($bracket, [0, 1, 2, 3]);
            $bracketCounts[$bracket]++;
        }

        foreach ($bracketCounts as $bracket => $count) {
            $this->assertSame(2, $count, "Bracket {$bracket} should have 2 matchups, got {$count}");
        }
    }

    /**
     * Pin-points the buggy logic that produced "expected 8 matchups, got 7" in
     * production. The bracket-3 tie containing the team originally at position
     * 16 had its higher seed drift to position 17 (a non-deterministic re-sort
     * of tied teams between playoff generation and R16 generation). With the
     * old logic, min(homePos=18, awayPos=17) = 17, no `higherPositions` array
     * contained 17, and the function silently returned bracket 0 — putting two
     * winners into bracket 0 and leaving bracket 3 empty.
     */
    public function test_old_min_based_classification_misclassifies_after_drift(): void
    {
        $oldFindPlayoffBracket = function (int $homePos, int $awayPos): int {
            $brackets = [
                [[9, 10], [23, 24]],
                [[11, 12], [21, 22]],
                [[13, 14], [19, 20]],
                [[15, 16], [17, 18]],
            ];
            $higherSeedPos = min($homePos, $awayPos);
            foreach ($brackets as $index => $bracket) {
                [$higherPositions] = $bracket;
                if (in_array($higherSeedPos, $higherPositions)) {
                    return $index;
                }
            }
            return 0;
        };

        $this->assertSame(
            0,
            $oldFindPlayoffBracket(homePos: 18, awayPos: 17),
            'Drifted bracket-3 tie falls through to default bracket 0',
        );
        $this->assertSame(
            1,
            $oldFindPlayoffBracket(homePos: 21, awayPos: 11),
            'Sanity: an undrifted bracket-1 tie still classifies correctly',
        );
    }

    /**
     * After the fix, bracket_position is stamped on each playoff CupTie at
     * creation time. R16 generation reads it directly, so the same drift
     * scenario that broke production now produces 8 matchups cleanly.
     */
    public function test_r16_succeeds_after_standings_drift_when_bracket_persisted(): void
    {
        $matchups = $this->generator->generateMatchups($this->game, $this->competition->id, 1);

        foreach ($matchups as $matchup) {
            [$home, $away, $bracket] = $matchup;
            $this->createTie(
                round: 1,
                home: $home,
                away: $away,
                bracketPosition: $bracket,
                winnerId: $away,
                completed: true,
            );
        }

        // Non-deterministic re-sort of two tied teams swaps positions 16 and 17.
        // Pre-fix this would have starved bracket 3 of one R16 opponent.
        $this->swapStandingsPositions(16, 17);

        $r16 = $this->generator->generateMatchups($this->game, $this->competition->id, 2);

        $this->assertCount(8, $r16);
    }

    /**
     * Production data created before this fix has bracket_position = NULL on
     * its playoff ties. The hardened findPlayoffBracket fallback uses
     * disjoint-set membership across each bracket's full position list
     * (higher + lower) so it tolerates within-bracket drift that the old
     * min-based check did not.
     */
    public function test_r16_succeeds_for_legacy_ties_with_drifted_standings(): void
    {
        foreach ($this->canonicalPlayoffPairings() as [$lowerPos, $higherPos]) {
            $this->createTie(
                round: 1,
                home: $this->teamsByPosition[$lowerPos]->id,
                away: $this->teamsByPosition[$higherPos]->id,
                bracketPosition: null,
                winnerId: $this->teamsByPosition[$higherPos]->id,
                completed: true,
            );
        }

        // Same drift as the persisted-bracket test, but here the fallback
        // legacy classifier is what has to absorb it.
        $this->swapStandingsPositions(16, 17);

        $r16 = $this->generator->generateMatchups($this->game, $this->competition->id, 2);

        $this->assertCount(8, $r16);
    }

    /**
     * Cross-bracket drift (e.g. a tied team at position 22 swapping with the
     * team at position 23) puts a tie's two teams in different brackets'
     * position sets, defeating disjoint-set membership in the legacy fallback
     * and starving the affected brackets. The deterministic redistribution
     * safety net guarantees R16 still generates 8 matchups so legacy games
     * can complete the tournament.
     */
    public function test_r16_succeeds_for_legacy_ties_with_cross_bracket_drift(): void
    {
        foreach ($this->canonicalPlayoffPairings() as [$lowerPos, $higherPos]) {
            $this->createTie(
                round: 1,
                home: $this->teamsByPosition[$lowerPos]->id,
                away: $this->teamsByPosition[$higherPos]->id,
                bracketPosition: null,
                winnerId: $this->teamsByPosition[$higherPos]->id,
                completed: true,
            );
        }

        // Position 22 (bracket 1) and 23 (bracket 0) swap on a re-sort —
        // the bracket-0 tie's lower-seed team now sits at position 22 and
        // the bracket-1 tie's lower-seed team at position 23. Neither
        // bracket's position set contains both teams of either tie.
        $this->swapStandingsPositions(22, 23);

        $r16 = $this->generator->generateMatchups($this->game, $this->competition->id, 2);

        $this->assertCount(8, $r16);
    }

    private function seedStandings(): void
    {
        for ($pos = 1; $pos <= 36; $pos++) {
            $team = Team::factory()->create();
            $this->teamsByPosition[$pos] = $team;

            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => $this->competition->id,
                'team_id' => $team->id,
                'position' => $pos,
                'played' => 8,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => max(0, 36 - $pos),
            ]);
        }
    }

    /** Canonical playoff pairings (lower seed home, higher seed away). */
    private function canonicalPlayoffPairings(): array
    {
        return [
            [23, 9], [24, 10],   // bracket 0
            [21, 11], [22, 12],  // bracket 1
            [19, 13], [20, 14],  // bracket 2
            [17, 15], [18, 16],  // bracket 3
        ];
    }

    private function createTie(
        int $round,
        string $home,
        string $away,
        ?int $bracketPosition,
        ?string $winnerId,
        bool $completed,
    ): CupTie {
        return CupTie::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => $round,
            'bracket_position' => $bracketPosition,
            'home_team_id' => $home,
            'away_team_id' => $away,
            'winner_id' => $winnerId,
            'completed' => $completed,
        ]);
    }

    private function swapStandingsPositions(int $a, int $b): void
    {
        $teamA = $this->teamsByPosition[$a];
        $teamB = $this->teamsByPosition[$b];

        GameStanding::where('game_id', $this->game->id)
            ->where('competition_id', $this->competition->id)
            ->where('team_id', $teamA->id)
            ->update(['position' => $b]);

        GameStanding::where('game_id', $this->game->id)
            ->where('competition_id', $this->competition->id)
            ->where('team_id', $teamB->id)
            ->update(['position' => $a]);

        $this->teamsByPosition[$a] = $teamB;
        $this->teamsByPosition[$b] = $teamA;
    }
}
