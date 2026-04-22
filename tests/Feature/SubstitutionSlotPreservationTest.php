<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Lineup\Services\TacticalChangeService;
use App\Modules\Match\DTOs\ResimulationResult;
use App\Modules\Match\Services\MatchResimulationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Regression: after the user drag-swaps two on-pitch players and the server
 * persists the new slot map, a subsequent substitution must not silently
 * re-run FormationRecommender from scratch. Doing so loses the drag-swap
 * intent and can push the incoming bench player (who is a natural fit for
 * the outgoing player's slot) into a non-natural slot with an out-of-position
 * penalty, while a versatile starter gets re-placed at their primary role.
 *
 * The fix in TacticalChangeService pins every player still on the pitch to
 * their currently-persisted slot before recomputing, so a straight sub only
 * rewrites the outgoing player's slot.
 */
class SubstitutionSlotPreservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_substitution_does_not_reshuffle_prior_drag_swap_positions(): void
    {
        $user = User::factory()->create();
        $playerTeam = Team::factory()->create();
        $opponentTeam = Team::factory()->create();

        $competition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $playerTeam->id,
            'competition_id' => $competition->id,
            'season' => '2024',
            'current_date' => '2024-09-01',
        ]);

        // Starting XI (4-3-3). Player A is the versatile RB/CM and is initially
        // at RB; Player B is a pure CM and is initially at CM.
        $gk = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Goalkeeper']);
        $lb = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Left-Back']);
        $cb1 = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Centre-Back']);
        $cb2 = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Centre-Back']);
        $playerA = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create([
            'position' => 'Right-Back',
            'secondary_positions' => ['Central Midfield'],
        ]);
        $cm2 = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Central Midfield']);
        $playerB = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create([
            'position' => 'Central Midfield',
            'secondary_positions' => [],
        ]);
        $cm3 = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Central Midfield']);
        $lw = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Left Winger']);
        $cf = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Centre-Forward']);
        $rw = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Right Winger']);

        // Bench: Player C is a natural RB that will replace Player B.
        $playerC = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create([
            'position' => 'Right-Back',
            'secondary_positions' => [],
        ]);

        // Opponent squad (needed by loadTeamsForResimulation).
        GamePlayer::factory()->count(11)->forGame($game)->forTeam($opponentTeam)->create();

        $lineupIds = [
            $gk->id, $lb->id, $cb1->id, $cb2->id, $playerA->id,
            $cm2->id, $playerB->id, $cm3->id,
            $lw->id, $cf->id, $rw->id,
        ];

        // Persisted slot_assignments reflects the POST-drag-swap state:
        // A is at slot 6 (CM, via his CM secondary); B is at slot 4 (RB, with
        // an out-of-position penalty because CM→RB compat is low).
        $postDragSwapSlots = [
            0 => $gk->id,       // GK
            1 => $lb->id,       // LB
            2 => $cb1->id,      // CB
            3 => $cb2->id,      // CB
            4 => $playerB->id,  // RB  ← B (non-natural, drag-swapped in)
            5 => $cm2->id,      // CM
            6 => $playerA->id,  // CM  ← A (drag-swapped in, uses CM secondary)
            7 => $cm3->id,      // CM
            8 => $lw->id,       // LW
            9 => $cf->id,       // CF
            10 => $rw->id,      // RW
        ];

        $match = GameMatch::factory()->create([
            'game_id' => $game->id,
            'competition_id' => $competition->id,
            'round_number' => 1,
            'home_team_id' => $playerTeam->id,
            'away_team_id' => $opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-09-01'),
            'played' => false,
            'home_formation' => '4-3-3',
            'home_lineup' => $lineupIds,
            'home_slot_assignments' => $postDragSwapSlots,
            'away_lineup' => GamePlayer::where('team_id', $opponentTeam->id)->pluck('id')->take(11)->all(),
            'away_formation' => '4-3-3',
        ]);

        $game->update(['pending_finalization_match_id' => $match->id]);

        // Swap out the resimulation service: this test is scoped to the slot-
        // assignment persistence that happens before the resimulation runs,
        // so a stub result is sufficient and keeps the setup light.
        $stubResult = new ResimulationResult(
            newHomeScore: 0,
            newAwayScore: 0,
            oldHomeScore: 0,
            oldAwayScore: 0,
        );
        $mockResimulation = Mockery::mock(MatchResimulationService::class);
        $mockResimulation->shouldReceive('resimulate')->andReturn($stubResult);
        $mockResimulation->shouldReceive('resimulateExtraTime')->andReturn($stubResult);
        $mockResimulation->shouldReceive('buildEventsResponse')->andReturn([]);
        $this->app->instance(MatchResimulationService::class, $mockResimulation);

        /** @var TacticalChangeService $service */
        $service = $this->app->make(TacticalChangeService::class);

        $service->processLiveMatchChanges(
            $match,
            $game,
            minute: 60,
            previousSubstitutions: [],
            newSubstitutions: [[
                'playerOutId' => $playerB->id,
                'playerInId' => $playerC->id,
            ]],
        );

        $match->refresh();
        $newSlots = $match->home_slot_assignments;

        // The incoming natural RB takes the RB slot that B occupied.
        $this->assertSame(
            $playerC->id,
            $newSlots[4] ?? null,
            'Natural-RB sub must enter the RB slot, not be pushed to a non-natural slot',
        );

        // Player A must stay at CM (where the user put him via drag-swap).
        // Without the fix, A would be re-placed at his primary RB slot — the
        // root cause of the penalty on the incoming player.
        $this->assertSame(
            $playerA->id,
            $newSlots[6] ?? null,
            'Versatile starter must stay at the CM slot the user chose',
        );

        // Every other starter keeps their slot.
        foreach ([0 => $gk->id, 1 => $lb->id, 2 => $cb1->id, 3 => $cb2->id,
                  5 => $cm2->id, 7 => $cm3->id, 8 => $lw->id, 9 => $cf->id,
                  10 => $rw->id] as $slotId => $expectedPlayerId) {
            $this->assertSame(
                $expectedPlayerId,
                $newSlots[$slotId] ?? null,
                "Slot {$slotId} must be preserved across the substitution",
            );
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
