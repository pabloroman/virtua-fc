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
 * Regression for #1161 — half-time formation change scrambling positions.
 *
 * When the user changes formation at half-time the frontend now sends the
 * FULL displayed XI (previewSlotMap) as manual_slot_pins, so the kickoff
 * lineup must be EXACTLY that arrangement — no further auto-adjustment by
 * FormationRecommender, even when a pin places a player out of position.
 *
 * This asserts the backend honors a complete pin map for a formation change:
 * every slot in the persisted home_slot_assignments matches the submitted
 * pins verbatim.
 */
class FormationChangeSlotPinningTest extends TestCase
{
    use RefreshDatabase;

    public function test_formation_change_with_full_pins_is_applied_verbatim(): void
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

        // Starting XI in 4-3-3.
        $gk = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Goalkeeper']);
        $lb = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Left-Back']);
        $cb1 = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Centre-Back']);
        $cb2 = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Centre-Back']);
        $rb = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Right-Back']);
        $cm1 = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Central Midfield']);
        $cm2 = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Central Midfield']);
        $cm3 = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Central Midfield']);
        $lw = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Left Winger']);
        $cf = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Centre-Forward']);
        $rw = GamePlayer::factory()->forGame($game)->forTeam($playerTeam)->create(['position' => 'Right Winger']);

        // Opponent squad (needed by loadTeamsForResimulation).
        GamePlayer::factory()->count(11)->forGame($game)->forTeam($opponentTeam)->create();

        $lineupIds = [
            $gk->id, $lb->id, $cb1->id, $cb2->id, $rb->id,
            $cm1->id, $cm2->id, $cm3->id,
            $lw->id, $cf->id, $rw->id,
        ];

        $startSlots = [
            0 => $gk->id, 1 => $lb->id, 2 => $cb1->id, 3 => $cb2->id, 4 => $rb->id,
            5 => $cm1->id, 6 => $cm2->id, 7 => $cm3->id,
            8 => $lw->id, 9 => $cf->id, 10 => $rw->id,
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
            'home_slot_assignments' => $startSlots,
            'away_lineup' => GamePlayer::where('team_id', $opponentTeam->id)->pluck('id')->take(11)->all(),
            'away_formation' => '4-3-3',
        ]);

        $game->update(['pending_finalization_match_id' => $match->id]);

        // The exact 5-3-2 arrangement the user confirmed at half-time. It is
        // deliberately NOT what the auto-solver would pick (the winger $lw is
        // pinned into a CB slot, $cf into the extra CB slot) so a verbatim
        // match proves the pins win over FormationRecommender.
        $confirmedPins = [
            0 => $gk->id,   // GK
            1 => $lb->id,   // LB
            2 => $cb1->id,  // CB
            3 => $cb2->id,  // CB
            4 => $lw->id,   // CB ← winger pinned out of position
            5 => $rb->id,   // RB
            6 => $cm1->id,  // CM
            7 => $cm2->id,  // CM
            8 => $cm3->id,  // CM
            9 => $cf->id,   // CF
            10 => $rw->id,  // CF
        ];

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
            minute: 45,
            previousSubstitutions: [],
            newSubstitutions: [],
            formation: '5-3-2',
            manualSlotPins: $confirmedPins,
            isHalfTime: true,
        );

        $match->refresh();

        $this->assertSame('5-3-2', $match->home_formation);

        // Every slot matches the confirmed arrangement verbatim — no slot was
        // re-solved or shuffled by the recommender.
        foreach ($confirmedPins as $slotId => $expectedPlayerId) {
            $this->assertSame(
                $expectedPlayerId,
                $match->home_slot_assignments[$slotId] ?? null,
                "Slot {$slotId} must match the user-confirmed lineup verbatim",
            );
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
