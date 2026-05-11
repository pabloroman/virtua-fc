<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameTactics;
use App\Models\Team;
use App\Models\User;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Services\LineupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the "lineup state is reconciled against the current squad on
 * every read" behaviour. The bug this guards against: a user saves a
 * lineup, one of those players later leaves the squad (transfer / retire /
 * long-term injury), and the lineup page renders the broken state (10
 * players in 11 slots, sometimes with the wrong outfielder in slot 0).
 *
 * `LineupService::reconcileLineupState` is the pure helper; `ShowLineup`
 * is the integration test that the helper is wired up correctly and that
 * `game.tactics` is updated so the broken state doesn't recur.
 */
class LineupReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $playerTeam;
    private Team $opponentTeam;
    private Competition $competition;
    private Game $game;
    private GameMatch $match;
    private LineupService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->playerTeam = Team::factory()->create(['name' => 'Player Team']);
        $this->opponentTeam = Team::factory()->create(['name' => 'Opponent Team']);

        $this->competition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->playerTeam->id,
            'competition_id' => $this->competition->id,
            'season' => '2024',
            'current_date' => '2024-09-01',
        ]);

        GameTactics::create([
            'game_id' => $this->game->id,
            'default_formation' => '4-3-3',
            'default_mentality' => 'balanced',
            'default_playing_style' => 'balanced',
            'default_pressing' => 'standard',
            'default_defensive_line' => 'normal',
        ]);

        $this->match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-09-01'),
            'played' => false,
        ]);

        $this->game->update(['pending_finalization_match_id' => $this->match->id]);

        $this->service = app(LineupService::class);
    }

    /**
     * Build a full 4-3-3 squad with every primary position filled. Returns
     * the starting XI as a Collection in slot order: GK, LB, CBs, RB,
     * CMs, LW, CF, RW.
     */
    private function buildSquad(): \Illuminate\Support\Collection
    {
        $spec = [
            'Goalkeeper',
            'Left-Back', 'Centre-Back', 'Centre-Back', 'Right-Back',
            'Central Midfield', 'Central Midfield', 'Central Midfield',
            'Left Winger', 'Centre-Forward', 'Right Winger',
        ];

        return collect($spec)->map(fn (string $pos) => GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->playerTeam)
            ->create(['position' => $pos, 'overall_score' => 75]));
    }

    private function addBenchPlayer(string $position, int $rating = 70): GamePlayer
    {
        return GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->playerTeam)
            ->create(['position' => $position, 'overall_score' => $rating]);
    }

    public function test_reconciler_is_idempotent_on_a_complete_valid_input(): void
    {
        $players = $this->buildSquad();
        $playerIds = $players->pluck('id')->all();
        $slotMap = $this->service->computeSlotAssignments(Formation::F_4_3_3, $players);

        $result = $this->service->reconcileLineupState(
            $playerIds,
            $slotMap,
            null,
            $players,
            $players,
            Formation::F_4_3_3,
        );

        $this->assertFalse($result['changed']);
        $this->assertSame([], $result['replaced']);
        $this->assertSame($playerIds, $result['lineup']);
        $this->assertCount(11, $result['slot_assignments']);
        foreach ($slotMap as $slotId => $playerId) {
            $this->assertSame($playerId, $result['slot_assignments'][(string) $slotId]);
        }
    }

    public function test_reconciler_replaces_stale_outfield_player_with_same_position_bench(): void
    {
        $players = $this->buildSquad();
        $playerIds = $players->pluck('id')->all();
        $slotMap = $this->service->computeSlotAssignments(Formation::F_4_3_3, $players);

        // Add a same-position backup to the bench.
        $backupLb = $this->addBenchPlayer('Left-Back', rating: 68);

        // Sell off the starting LB — simulate by detaching from team.
        $players[1]->update(['team_id' => $this->opponentTeam->id]);
        // Refresh team players: starting LB is now gone, backup LB is in the pool.
        $available = collect($players->slice(2))
            ->concat([$backupLb])
            ->push($players[0]); // re-add GK
        $allTeam = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->get();
        $available = $allTeam; // all are available (no injuries set)

        $result = $this->service->reconcileLineupState(
            $playerIds,
            $slotMap,
            null,
            $available,
            $allTeam,
            Formation::F_4_3_3,
        );

        $this->assertTrue($result['changed']);
        $this->assertCount(1, $result['replaced']);
        $this->assertSame($players[1]->id, $result['replaced'][0]['out_id']);
        $this->assertSame($backupLb->id, $result['replaced'][0]['in_id']);
        $this->assertCount(11, $result['lineup']);
        $this->assertContains($backupLb->id, $result['lineup']);
        $this->assertNotContains($players[1]->id, $result['lineup']);
        // The replacement must land at the LB slot the recommender picks.
        $this->assertContains($backupLb->id, $result['slot_assignments']);
    }

    public function test_reconciler_replaces_stale_goalkeeper_with_backup_goalkeeper(): void
    {
        $players = $this->buildSquad();
        $playerIds = $players->pluck('id')->all();
        $slotMap = $this->service->computeSlotAssignments(Formation::F_4_3_3, $players);

        // Add a backup goalkeeper.
        $backupGk = $this->addBenchPlayer('Goalkeeper', rating: 65);

        // Starting GK gets transferred out.
        $players[0]->update(['team_id' => $this->opponentTeam->id]);

        $allTeam = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->get();

        $result = $this->service->reconcileLineupState(
            $playerIds,
            $slotMap,
            null,
            $allTeam,
            $allTeam,
            Formation::F_4_3_3,
        );

        $this->assertTrue($result['changed']);
        $this->assertSame($backupGk->id, $result['slot_assignments']['0']);
        $this->assertCount(11, $result['slot_assignments']);
        // The "out" entry must reference the sold GK; the "in" entry the backup.
        $replacementForGk = collect($result['replaced'])
            ->firstWhere('out_id', $players[0]->id);
        $this->assertNotNull($replacementForGk);
        $this->assertSame($backupGk->id, $replacementForGk['in_id']);
    }

    public function test_reconciler_preserves_user_pinned_outfield_slot(): void
    {
        $players = $this->buildSquad();

        // Build a custom slot map: put the Right Winger at the Left Winger
        // slot (an explicit user drag-swap, allowed in the UI).
        $rw = $players[10];
        $lw = $players[8];
        $rw->update(['secondary_positions' => ['Left Winger']]);
        $slotMap = $this->service->computeSlotAssignments(
            Formation::F_4_3_3,
            $players,
            ['8' => $rw->id, '10' => $lw->id],
        );

        $playerIds = $players->pluck('id')->all();

        // Now sell the LB; reconcile.
        $players[1]->update(['team_id' => $this->opponentTeam->id]);
        $allTeam = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->get();

        $result = $this->service->reconcileLineupState(
            $playerIds,
            $slotMap,
            null,
            $allTeam,
            $allTeam,
            Formation::F_4_3_3,
        );

        $this->assertTrue($result['changed']);
        $this->assertSame($rw->id, $result['slot_assignments']['8'], 'Pinned RW-at-LW must survive');
        $this->assertSame($lw->id, $result['slot_assignments']['10'], 'Pinned LW-at-RW must survive');
    }

    public function test_reconciler_filters_pitch_positions_to_formation_slot_ids(): void
    {
        $players = $this->buildSquad();
        $playerIds = $players->pluck('id')->all();
        $slotMap = $this->service->computeSlotAssignments(Formation::F_4_3_3, $players);

        // Saved pitch positions include an entry for slot 11, which doesn't
        // exist in any 11-slot formation. The reconciler must drop it.
        $pitchPositions = [
            '0' => [4, 1],
            '5' => [3, 7],
            '11' => [9, 9],
        ];

        // Force a reconciliation by removing one player so `changed` flips.
        $players[1]->update(['team_id' => $this->opponentTeam->id]);
        $allTeam = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->get();
        $backupLb = $this->addBenchPlayer('Left-Back');
        $allTeam = $allTeam->push($backupLb);

        $result = $this->service->reconcileLineupState(
            $playerIds,
            $slotMap,
            $pitchPositions,
            $allTeam,
            $allTeam,
            Formation::F_4_3_3,
        );

        $this->assertArrayHasKey('0', $result['pitch_positions']);
        $this->assertArrayHasKey('5', $result['pitch_positions']);
        $this->assertArrayNotHasKey('11', $result['pitch_positions']);
    }

    public function test_show_lineup_persists_reconciled_state_back_to_tactics(): void
    {
        $players = $this->buildSquad();
        $playerIds = $players->pluck('id')->all();
        $slotMap = $this->service->computeSlotAssignments(Formation::F_4_3_3, $players);
        $backupLb = $this->addBenchPlayer('Left-Back');

        // Persist a saved lineup into game.tactics, then transfer the LB
        // out — exactly the broken-state scenario from the bug report.
        $this->game->tactics->update([
            'default_lineup' => $playerIds,
            'default_slot_assignments' => $slotMap,
            'default_formation' => '4-3-3',
        ]);

        $players[1]->update(['team_id' => $this->opponentTeam->id]);

        $response = $this->actingAs($this->user)->get(route('game.lineup', $this->game->id));
        $response->assertOk();

        $this->game->tactics->refresh();

        $newDefaults = $this->game->tactics->default_lineup;
        $newSlotMap = $this->game->tactics->default_slot_assignments;

        $this->assertCount(11, $newDefaults);
        $this->assertNotContains($players[1]->id, $newDefaults, 'Sold LB must be out of the saved defaults');
        $this->assertContains($backupLb->id, $newDefaults, 'Backup LB must be in the saved defaults');
        $this->assertCount(11, $newSlotMap);
    }

    public function test_show_lineup_does_not_touch_tactics_for_brand_new_games(): void
    {
        // No saved lineup yet — tactics.default_lineup is null.
        $this->assertNull($this->game->tactics->default_lineup);

        $this->buildSquad();

        $response = $this->actingAs($this->user)->get(route('game.lineup', $this->game->id));
        $response->assertOk();

        $this->game->tactics->refresh();
        $this->assertNull($this->game->tactics->default_lineup, 'Brand-new games must keep falling back to autoSelect');
    }

    public function test_validate_lineup_rejects_slot_map_with_non_goalkeeper_at_gk_slot(): void
    {
        $players = $this->buildSquad();
        $playerIds = $players->pluck('id')->all();

        // Put an outfield player at slot 0, push the actual GK to a CB slot.
        $badSlotMap = $this->service->computeSlotAssignments(Formation::F_4_3_3, $players);
        $gkId = $players[0]->id;
        $cbId = $players[2]->id;
        $badSlotMap['0'] = $cbId;
        $badSlotMap['2'] = $gkId;

        $errors = $this->service->validateLineup(
            $playerIds,
            $this->game->id,
            $this->playerTeam->id,
            $this->match->scheduled_date,
            $this->competition->id,
            Formation::F_4_3_3,
            $badSlotMap,
        );

        $this->assertNotEmpty($errors);
        $this->assertContains(__('squad.formation_gk_slot_must_be_goalkeeper'), $errors);
    }

    public function test_validate_lineup_rejects_incomplete_slot_map(): void
    {
        $players = $this->buildSquad();
        $playerIds = $players->pluck('id')->all();

        $partialSlotMap = $this->service->computeSlotAssignments(Formation::F_4_3_3, $players);
        unset($partialSlotMap['6']); // drop a CM slot

        $errors = $this->service->validateLineup(
            $playerIds,
            $this->game->id,
            $this->playerTeam->id,
            $this->match->scheduled_date,
            $this->competition->id,
            Formation::F_4_3_3,
            $partialSlotMap,
        );

        $this->assertContains(__('squad.formation_slot_map_incomplete'), $errors);
    }

    public function test_validate_lineup_rejects_slot_map_with_duplicate_player(): void
    {
        $players = $this->buildSquad();
        $playerIds = $players->pluck('id')->all();

        $slotMap = $this->service->computeSlotAssignments(Formation::F_4_3_3, $players);
        // Force a duplicate: pretend slot 5 and slot 6 are the same player.
        $slotMap['6'] = $slotMap['5'];

        $errors = $this->service->validateLineup(
            $playerIds,
            $this->game->id,
            $this->playerTeam->id,
            $this->match->scheduled_date,
            $this->competition->id,
            Formation::F_4_3_3,
            $slotMap,
        );

        $this->assertContains(__('squad.formation_slot_map_duplicates'), $errors);
    }
}
