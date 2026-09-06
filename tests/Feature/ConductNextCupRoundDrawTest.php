<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Exceptions\OddCupDrawPoolException;
use App\Modules\Match\Events\CupTieResolved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Tests\TestCase;

/**
 * Verifies the listener swallows OddCupDrawPoolException so that legacy
 * games with brackets corrupted by the pre-fix truncation (see commit
 * b644bc961) can still finalize matches. The cup quietly stops drawing
 * further rounds; downstream supercup/UEFA paths handle the missing
 * final via existing fallbacks.
 */
class ConductNextCupRoundDrawTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Competition $cup;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
            'tier' => 1,
        ]);

        $this->cup = Competition::factory()->knockoutCup()->create([
            'id' => 'ESPCUP',
            'name' => 'Copa del Rey',
            'season' => '2025',
        ]);

        $user = User::factory()->create();
        $userTeam = Team::factory()->create();

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
        ]);
    }

    public function test_swallows_odd_pool_exception_so_match_finalization_can_complete(): void
    {
        Exceptions::fake();

        // Stage round 1 with 3 completed ties (6 teams → 3 winners). The
        // round-2 pool would be 3 (odd), so conductDraw must throw — and
        // the listener must swallow that throw instead of propagating it
        // up through match finalization.
        $teams = Team::factory()->count(6)->create();
        foreach ($teams as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESPCUP',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        $resolvedTie = null;
        $resolvedMatch = null;
        for ($i = 0; $i < 3; $i++) {
            $home = $teams[$i * 2];
            $away = $teams[$i * 2 + 1];

            $tie = CupTie::factory()
                ->forGame($this->game)
                ->inRound(1)
                ->between($home, $away)
                ->completed($home)
                ->create(['competition_id' => 'ESPCUP']);

            $match = GameMatch::factory()->create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESPCUP',
                'round_number' => 1,
                'home_team_id' => $home->id,
                'away_team_id' => $away->id,
                'cup_tie_id' => $tie->id,
                'played' => true,
            ]);

            $tie->update(['first_leg_match_id' => $match->id]);

            $resolvedTie = $tie;
            $resolvedMatch = $match;
        }

        // Dispatching CupTieResolved fires the listener. If it didn't
        // swallow the exception, this would throw — failing the test.
        CupTieResolved::dispatch(
            $resolvedTie,
            $resolvedTie->winner_id,
            $resolvedMatch,
            $this->game,
            $this->cup,
        );

        // Listener completed without propagating; verify no round-2 ties
        // were created (the cup quietly stopped) and the exception was
        // reported for observability.
        $this->assertSame(
            0,
            CupTie::where('game_id', $this->game->id)
                ->where('competition_id', 'ESPCUP')
                ->where('round_number', 2)
                ->count(),
            'No round-2 ties should be created when the pool is odd'
        );

        Exceptions::assertReported(OddCupDrawPoolException::class);

        // The reveal is recorded inside the same try, after conductDraw(), so an
        // abandoned bracket must not queue a ceremony for a round that has no ties.
        $this->assertNull(
            $this->game->fresh()->pending_draw_reveal,
            'A swallowed draw must not queue a ceremony'
        );
    }

    public function test_does_not_report_when_no_next_round_is_due(): void
    {
        // Sanity / negative control: when no draw is needed (e.g. the
        // resolved tie is the only one and no further rounds exist),
        // handle() short-circuits and nothing is reported. This guards
        // against the catch quietly hiding real errors in the happy path.
        Exceptions::fake();

        [$home, $away] = Team::factory()->count(2)->create()->all();

        $tie = CupTie::factory()
            ->forGame($this->game)
            ->inRound(7) // cup.final — no round 8 in schedule.json
            ->between($home, $away)
            ->completed($home)
            ->create(['competition_id' => 'ESPCUP']);

        $match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESPCUP',
            'round_number' => 7,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'cup_tie_id' => $tie->id,
            'played' => true,
        ]);

        CupTieResolved::dispatch($tie, $tie->winner_id, $match, $this->game, $this->cup);

        Exceptions::assertNothingReported();
    }
}
