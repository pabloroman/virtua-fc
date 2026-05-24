<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Services\PlayoffTiebreakerService;
use App\Modules\Match\Services\CupTieResolver;
use App\Modules\Match\Services\ExtraTimeAndPenaltyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the Spain promotion-playoff tiebreaker: when a two-legged tie is
 * level after extra time, the higher-regular-season-finishing team goes
 * through — no penalties. Applies to ESP2 → ESP1 and ESP3 → ESP2 playoffs.
 * Domestic cups (ESPCUP) and anything else still go to penalties.
 */
class PlayoffTiebreakerTest extends TestCase
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
        Competition::factory()->knockoutCup()->create([
            'id' => 'ESPCUP', 'tier' => 1,
        ]);

        $user = User::factory()->create();
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => Team::factory()->create()->id,
            'competition_id' => 'ESP2',
            'season' => '2025',
            'current_date' => '2026-06-14',
        ]);
    }

    // ─────────────────────────────────────────────────
    // PlayoffTiebreakerService::appliesTo
    // ─────────────────────────────────────────────────

    public function test_applies_to_esp2_playoff_tie(): void
    {
        $tie = $this->makeEmptyTie('ESP2');

        $this->assertTrue(app(PlayoffTiebreakerService::class)->appliesTo($tie));
    }

    public function test_applies_to_esp3po_playoff_tie(): void
    {
        $tie = $this->makeEmptyTie('ESP3PO');

        $this->assertTrue(app(PlayoffTiebreakerService::class)->appliesTo($tie));
    }

    public function test_does_not_apply_to_espcup_tie(): void
    {
        $tie = $this->makeEmptyTie('ESPCUP');

        $this->assertFalse(app(PlayoffTiebreakerService::class)->appliesTo($tie));
    }

    // ─────────────────────────────────────────────────
    // PlayoffTiebreakerService::resolveWinner
    // ─────────────────────────────────────────────────

    public function test_resolve_winner_returns_higher_finisher_in_same_league(): void
    {
        $third = Team::factory()->create();
        $sixth = Team::factory()->create();
        $this->createStanding('ESP2', $third->id, position: 3);
        $this->createStanding('ESP2', $sixth->id, position: 6);

        $tie = $this->makeEmptyTie('ESP2', $third, $sixth);

        $this->assertEquals(
            $third->id,
            app(PlayoffTiebreakerService::class)->resolveWinner($tie, $this->game),
        );
    }

    public function test_resolve_winner_returns_higher_finisher_when_slots_swapped(): void
    {
        // Lower seed usually hosts the first leg — i.e. is the tie's home team.
        // The tiebreaker must still pick the higher seed on the away slot.
        $sixth = Team::factory()->create();
        $second = Team::factory()->create();
        $this->createStanding('ESP2', $sixth->id, position: 6);
        $this->createStanding('ESP2', $second->id, position: 2);

        $tie = $this->makeEmptyTie('ESP2', $sixth, $second);

        $this->assertEquals(
            $second->id,
            app(PlayoffTiebreakerService::class)->resolveWinner($tie, $this->game),
        );
    }

    public function test_resolve_winner_handles_esp3po_cross_group_standings(): void
    {
        // ESP3PO ties pull from both feeder groups (ESP3A and ESP3B).
        $a2 = Team::factory()->create();
        $b5 = Team::factory()->create();
        $this->createStanding('ESP3A', $a2->id, position: 2);
        $this->createStanding('ESP3B', $b5->id, position: 5);

        $tie = $this->makeEmptyTie('ESP3PO', $b5, $a2);

        $this->assertEquals(
            $a2->id,
            app(PlayoffTiebreakerService::class)->resolveWinner($tie, $this->game),
        );
    }

    public function test_resolve_winner_falls_back_to_simulated_season(): void
    {
        // ESP3B has no real standings (sister group the player isn't in) —
        // finishing position must come from SimulatedSeason.results.
        $a3 = Team::factory()->create();
        $b4 = Team::factory()->create();
        $this->createStanding('ESP3A', $a3->id, position: 3);

        $simResults = [];
        for ($i = 0; $i < 20; $i++) {
            $simResults[] = $i === 3 ? $b4->id : Team::factory()->create()->id;
        }
        SimulatedSeason::create([
            'game_id' => $this->game->id,
            'season' => $this->game->season,
            'competition_id' => 'ESP3B',
            'results' => $simResults,
        ]);

        $tie = $this->makeEmptyTie('ESP3PO', $b4, $a3);

        // A3 (position 3) beats B4 (simulated index 3 → position 4).
        $this->assertEquals(
            $a3->id,
            app(PlayoffTiebreakerService::class)->resolveWinner($tie, $this->game),
        );
    }

    public function test_resolve_winner_returns_null_for_espcup(): void
    {
        $tie = $this->makeEmptyTie('ESPCUP');

        $this->assertNull(
            app(PlayoffTiebreakerService::class)->resolveWinner($tie, $this->game),
        );
    }

    public function test_resolve_winner_returns_null_when_positions_missing(): void
    {
        // No GameStanding, no SimulatedSeason — positions can't be determined,
        // so the caller falls back to penalties.
        $tie = $this->makeEmptyTie('ESP2');

        $this->assertNull(
            app(PlayoffTiebreakerService::class)->resolveWinner($tie, $this->game),
        );
    }

    // ─────────────────────────────────────────────────
    // ExtraTimeAndPenaltyService::checkNeedsPenalties
    // ─────────────────────────────────────────────────

    public function test_check_needs_penalties_false_for_level_esp2_playoff_tie(): void
    {
        $higherSeed = Team::factory()->create();
        $lowerSeed = Team::factory()->create();
        $this->createStanding('ESP2', $higherSeed->id, position: 3);
        $this->createStanding('ESP2', $lowerSeed->id, position: 6);

        [, $secondLeg] = $this->makeTwoLeggedTie(
            'ESP2',
            tieHomeTeam: $lowerSeed,
            tieAwayTeam: $higherSeed,
            firstLegHome: 1,
            firstLegAway: 1,
            secondLegHome: 1,
            secondLegAway: 1,
        );

        $result = app(ExtraTimeAndPenaltyService::class)
            ->checkNeedsPenalties($secondLeg, 0, 0);

        $this->assertFalse(
            $result,
            'ESP2 playoff ties level after ET must skip penalties in favor of regular-season position',
        );
    }

    public function test_check_needs_penalties_true_for_level_espcup_tie(): void
    {
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        [, $secondLeg] = $this->makeTwoLeggedTie(
            'ESPCUP',
            tieHomeTeam: $homeTeam,
            tieAwayTeam: $awayTeam,
            firstLegHome: 1,
            firstLegAway: 1,
            secondLegHome: 1,
            secondLegAway: 1,
        );

        $result = app(ExtraTimeAndPenaltyService::class)
            ->checkNeedsPenalties($secondLeg, 0, 0);

        $this->assertTrue(
            $result,
            'Domestic cup ties must still go to penalties when level after ET',
        );
    }

    public function test_check_needs_penalties_false_when_aggregate_decided(): void
    {
        // Guard: even for a playoff tie, an ET result that breaks the
        // aggregate tie shouldn't touch penalties either way.
        $higherSeed = Team::factory()->create();
        $lowerSeed = Team::factory()->create();
        $this->createStanding('ESP2', $higherSeed->id, position: 3);
        $this->createStanding('ESP2', $lowerSeed->id, position: 6);

        [, $secondLeg] = $this->makeTwoLeggedTie(
            'ESP2',
            tieHomeTeam: $lowerSeed,
            tieAwayTeam: $higherSeed,
            firstLegHome: 1,
            firstLegAway: 1,
            secondLegHome: 1,
            secondLegAway: 0,
        );

        // 2nd leg ET 1-0 for home (lower seed, as 2nd-leg home) → aggregate home 1+0=1, away 1+1+1=3.
        $result = app(ExtraTimeAndPenaltyService::class)
            ->checkNeedsPenalties($secondLeg, 1, 0);

        $this->assertFalse($result);
    }

    // ─────────────────────────────────────────────────
    // CupTieResolver: full resolution path
    // ─────────────────────────────────────────────────

    public function test_cup_tie_resolver_awards_higher_seed_when_level_after_et_in_esp2(): void
    {
        $higherSeed = Team::factory()->create();
        $lowerSeed = Team::factory()->create();
        $this->createStanding('ESP2', $higherSeed->id, position: 3);
        $this->createStanding('ESP2', $lowerSeed->id, position: 6);

        [$tie, $secondLeg] = $this->makeTwoLeggedTie(
            'ESP2',
            tieHomeTeam: $lowerSeed,
            tieAwayTeam: $higherSeed,
            firstLegHome: 1,
            firstLegAway: 1,
            secondLegHome: 1,
            secondLegAway: 1,
            withExtraTime: true,
        );

        $winnerId = app(CupTieResolver::class)->resolve($tie->fresh(), collect());

        $this->assertEquals($higherSeed->id, $winnerId);

        $tie->refresh();
        $this->assertTrue($tie->completed);
        $this->assertEquals($higherSeed->id, $tie->winner_id);
        $this->assertEquals('higher_seed', $tie->resolution['type']);
        $this->assertEquals('2-2', $tie->resolution['aggregate']);

        // Penalties must NOT be simulated for a promotion-playoff tie.
        $secondLeg->refresh();
        $this->assertNull($secondLeg->home_score_penalties);
        $this->assertNull($secondLeg->away_score_penalties);
    }

    public function test_cup_tie_resolver_awards_higher_seed_in_esp3po_cross_group(): void
    {
        $a2 = Team::factory()->create(); // Group A, 2nd
        $b5 = Team::factory()->create(); // Group B, 5th
        $this->createStanding('ESP3A', $a2->id, position: 2);
        $this->createStanding('ESP3B', $b5->id, position: 5);

        // Both legs 1-1 → aggregate 2-2 after ET, so the tie reaches the
        // regular-season tiebreaker rather than being decided on aggregate.
        [$tie, $secondLeg] = $this->makeTwoLeggedTie(
            'ESP3PO',
            tieHomeTeam: $b5,
            tieAwayTeam: $a2,
            firstLegHome: 1,
            firstLegAway: 1,
            secondLegHome: 1,
            secondLegAway: 1,
            withExtraTime: true,
        );

        $winnerId = app(CupTieResolver::class)->resolve($tie->fresh(), collect());

        $this->assertEquals($a2->id, $winnerId);
        $tie->refresh();
        $this->assertEquals('higher_seed', $tie->resolution['type']);

        $secondLeg->refresh();
        $this->assertNull($secondLeg->home_score_penalties);
    }

    public function test_cup_tie_resolver_uses_penalties_for_espcup(): void
    {
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        [$tie, $secondLeg] = $this->makeTwoLeggedTie(
            'ESPCUP',
            tieHomeTeam: $homeTeam,
            tieAwayTeam: $awayTeam,
            firstLegHome: 1,
            firstLegAway: 1,
            secondLegHome: 1,
            secondLegAway: 1,
            withExtraTime: true,
            secondLegRound: 6, // ESPCUP round 6 is the two-legged semi-final
        );

        // Pre-set penalties so the resolver doesn't try to simulate (which
        // would need full squads); we only care which branch it takes.
        $secondLeg->update([
            'home_score_penalties' => 5,
            'away_score_penalties' => 3,
        ]);

        $winnerId = app(CupTieResolver::class)->resolve($tie->fresh(), collect());

        // 2nd-leg home team won on pens → that's the tie's away team.
        $this->assertEquals($awayTeam->id, $winnerId);
        $tie->refresh();
        $this->assertEquals('penalties', $tie->resolution['type']);
    }

    public function test_cup_tie_resolver_falls_back_to_penalties_when_positions_missing(): void
    {
        // Playoff competition but no standings for either team — safe fallback
        // is penalties rather than arbitrarily picking one side.
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        [$tie, $secondLeg] = $this->makeTwoLeggedTie(
            'ESP2',
            tieHomeTeam: $homeTeam,
            tieAwayTeam: $awayTeam,
            firstLegHome: 1,
            firstLegAway: 1,
            secondLegHome: 1,
            secondLegAway: 1,
            withExtraTime: true,
        );

        $secondLeg->update([
            'home_score_penalties' => 4,
            'away_score_penalties' => 5,
        ]);

        $winnerId = app(CupTieResolver::class)->resolve($tie->fresh(), collect());

        $this->assertEquals($homeTeam->id, $winnerId);
        $tie->refresh();
        $this->assertEquals('penalties', $tie->resolution['type']);
    }

    // ─────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────

    private function makeEmptyTie(string $competitionId, ?Team $home = null, ?Team $away = null): CupTie
    {
        return CupTie::factory()
            ->forGame($this->game)
            ->between($home ?? Team::factory()->create(), $away ?? Team::factory()->create())
            ->create(['competition_id' => $competitionId]);
    }

    /**
     * @return array{0: CupTie, 1: GameMatch}
     */
    private function makeTwoLeggedTie(
        string $competitionId,
        Team $tieHomeTeam,
        Team $tieAwayTeam,
        int $firstLegHome,
        int $firstLegAway,
        int $secondLegHome,
        int $secondLegAway,
        bool $withExtraTime = false,
        int $secondLegRound = 1,
    ): array {
        $firstLeg = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $competitionId,
            'round_number' => $secondLegRound,
            'home_team_id' => $tieHomeTeam->id,
            'away_team_id' => $tieAwayTeam->id,
            'scheduled_date' => Carbon::parse('2026-06-07'),
            'played' => true,
            'home_score' => $firstLegHome,
            'away_score' => $firstLegAway,
        ]);

        $tie = CupTie::factory()
            ->forGame($this->game)
            ->between($tieHomeTeam, $tieAwayTeam)
            ->inRound($secondLegRound)
            ->create([
                'competition_id' => $competitionId,
                'first_leg_match_id' => $firstLeg->id,
            ]);

        $secondLegAttrs = [
            'game_id' => $this->game->id,
            'competition_id' => $competitionId,
            'round_number' => $secondLegRound,
            // Teams swap on the second leg.
            'home_team_id' => $tieAwayTeam->id,
            'away_team_id' => $tieHomeTeam->id,
            'scheduled_date' => Carbon::parse('2026-06-14'),
            'played' => true,
            'home_score' => $secondLegHome,
            'away_score' => $secondLegAway,
            'cup_tie_id' => $tie->id,
        ];

        if ($withExtraTime) {
            $secondLegAttrs['home_score_et'] = 0;
            $secondLegAttrs['away_score_et'] = 0;
        }

        $secondLeg = GameMatch::factory()->create($secondLegAttrs);

        $tie->update(['second_leg_match_id' => $secondLeg->id]);

        return [$tie->fresh(), $secondLeg->fresh()];
    }

    private function createStanding(string $competitionId, string $teamId, int $position): void
    {
        GameStanding::create([
            'game_id' => $this->game->id,
            'competition_id' => $competitionId,
            'team_id' => $teamId,
            'position' => $position,
            'played' => 38,
            'won' => max(0, 25 - $position),
            'drawn' => 5,
            'lost' => max(0, $position + 5),
            'goals_for' => max(10, 80 - $position * 2),
            'goals_against' => 20 + $position,
            'points' => max(0, 80 - $position * 3),
        ]);
    }
}
