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
 * End-to-end coverage for the slot-map persistence flow introduced by the
 * "single source of truth" refactor:
 *
 *   - SaveLineup writes slot_assignments to game_matches (not just tactics).
 *   - LineupService::resolveSlotAssignments returns the persisted map when
 *     present and lazy-computes otherwise, without mutating the DB.
 *   - The ComputeSlotAssignments endpoint runs the authoritative algorithm
 *     and honors manual pins.
 */
class LineupSlotPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $playerTeam;
    private Team $opponentTeam;
    private Competition $competition;
    private Game $game;
    private GameMatch $match;

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
    }

    /**
     * Build an 11-man 4-3-3 squad with natural primary positions for every slot.
     * Returns the created GamePlayer models as a Collection.
     */
    private function makeEleven(): \Illuminate\Support\Collection
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
            ->create(['position' => $pos]));
    }

    public function test_save_lineup_persists_slot_assignments_to_match_row(): void
    {
        $players = $this->makeEleven();
        $playerIds = $players->pluck('id')->all();

        /** @var LineupService $service */
        $service = app(LineupService::class);

        $service->saveLineup(
            $this->match,
            $this->playerTeam->id,
            $playerIds,
            Formation::F_4_3_3,
            // Deliberately omit the slot map so we exercise the server-side
            // compute path.
        );

        $this->match->refresh();

        $this->assertSame($playerIds, $this->match->home_lineup);
        $this->assertSame('4-3-3', $this->match->home_formation);
        $this->assertIsArray($this->match->home_slot_assignments);
        $this->assertCount(11, $this->match->home_slot_assignments);

        // Every slot id (0..10) in F_4_3_3 must be keyed to one of our players.
        foreach (range(0, 10) as $slotId) {
            $this->assertArrayHasKey((string) $slotId, $this->match->home_slot_assignments);
            $this->assertContains(
                $this->match->home_slot_assignments[(string) $slotId],
                $playerIds,
            );
        }
    }

    public function test_save_lineup_trusts_caller_provided_slot_map(): void
    {
        $players = $this->makeEleven();
        $playerIds = $players->pluck('id')->all();

        /** @var LineupService $service */
        $service = app(LineupService::class);

        // Build a manual slot map where the GK goes in slot 0. We trust it
        // as-is without recomputing.
        $manualMap = [
            '0' => $playerIds[0], // GK
            '1' => $playerIds[1],
            '2' => $playerIds[2],
        ];

        $service->saveLineup(
            $this->match,
            $this->playerTeam->id,
            $playerIds,
            Formation::F_4_3_3,
            $manualMap,
        );

        $this->match->refresh();

        $this->assertSame($manualMap, $this->match->home_slot_assignments);
    }

    public function test_resolve_slot_assignments_lazy_computes_when_match_has_null_map(): void
    {
        $players = $this->makeEleven();
        $playerIds = $players->pluck('id')->all();

        // Populate lineup + formation directly on the match row but leave
        // slot_assignments NULL, simulating a match that predates this
        // refactor.
        $this->match->update([
            'home_lineup' => $playerIds,
            'home_formation' => '4-3-3',
            'home_slot_assignments' => null,
        ]);

        /** @var LineupService $service */
        $service = app(LineupService::class);

        $resolved = $service->resolveSlotAssignments($this->match, $this->playerTeam->id);

        $this->assertIsArray($resolved);
        $this->assertCount(11, $resolved);
        foreach (range(0, 10) as $slotId) {
            $this->assertArrayHasKey((string) $slotId, $resolved);
        }

        // Lazy compute must NOT persist — read paths are side-effect-free.
        $this->match->refresh();
        $this->assertNull($this->match->home_slot_assignments);
    }

    public function test_resolve_slot_assignments_returns_persisted_map_when_present(): void
    {
        $players = $this->makeEleven();
        $playerIds = $players->pluck('id')->all();

        $stored = ['5' => $playerIds[0]];
        $this->match->update([
            'home_lineup' => $playerIds,
            'home_formation' => '4-3-3',
            'home_slot_assignments' => $stored,
        ]);

        /** @var LineupService $service */
        $service = app(LineupService::class);

        $resolved = $service->resolveSlotAssignments($this->match, $this->playerTeam->id);

        $this->assertSame($stored, $resolved);
    }

    public function test_compute_slot_assignments_endpoint_returns_full_map(): void
    {
        $players = $this->makeEleven();
        $playerIds = $players->pluck('id')->all();

        $response = $this->actingAs($this->user)->postJson(
            route('game.lineup.computeSlots', $this->game->id),
            [
                'formation' => '4-3-3',
                'player_ids' => $playerIds,
            ],
        );

        $response->assertOk();
        $response->assertJsonStructure(['slot_assignments', 'formation']);
        $response->assertJson(['formation' => '4-3-3']);

        $map = $response->json('slot_assignments');
        $this->assertIsArray($map);
        $this->assertCount(11, $map);

        foreach (range(0, 10) as $slotId) {
            $this->assertArrayHasKey((string) $slotId, $map);
            $this->assertContains($map[(string) $slotId], $playerIds);
        }
    }

    public function test_compute_slot_assignments_endpoint_honors_manual_pins(): void
    {
        // Build a squad where a player's primary is CF but they can also
        // play LW (secondary). Pinning them to LW must stick even though the
        // algorithm would otherwise put them at CF.
        $gk = GamePlayer::factory()->forGame($this->game)->forTeam($this->playerTeam)->create(['position' => 'Goalkeeper']);
        $lb = GamePlayer::factory()->forGame($this->game)->forTeam($this->playerTeam)->create(['position' => 'Left-Back']);
        $cb1 = GamePlayer::factory()->forGame($this->game)->forTeam($this->playerTeam)->create(['position' => 'Centre-Back']);
        $cb2 = GamePlayer::factory()->forGame($this->game)->forTeam($this->playerTeam)->create(['position' => 'Centre-Back']);
        $rb = GamePlayer::factory()->forGame($this->game)->forTeam($this->playerTeam)->create(['position' => 'Right-Back']);
        $cm1 = GamePlayer::factory()->forGame($this->game)->forTeam($this->playerTeam)->create(['position' => 'Central Midfield']);
        $cm2 = GamePlayer::factory()->forGame($this->game)->forTeam($this->playerTeam)->create(['position' => 'Central Midfield']);
        $cm3 = GamePlayer::factory()->forGame($this->game)->forTeam($this->playerTeam)->create(['position' => 'Central Midfield']);
        $mbappe = GamePlayer::factory()->forGame($this->game)->forTeam($this->playerTeam)->create([
            'position' => 'Centre-Forward',
            'secondary_positions' => ['Left Winger', 'Right Winger'],
        ]);
        $vinicius = GamePlayer::factory()->forGame($this->game)->forTeam($this->playerTeam)->create([
            'position' => 'Left Winger',
            'secondary_positions' => ['Centre-Forward'],
        ]);
        $rodrygo = GamePlayer::factory()->forGame($this->game)->forTeam($this->playerTeam)->create([
            'position' => 'Right Winger',
            'secondary_positions' => ['Left Winger', 'Centre-Forward'],
        ]);

        $playerIds = [
            $gk->id, $lb->id, $cb1->id, $cb2->id, $rb->id,
            $cm1->id, $cm2->id, $cm3->id, $mbappe->id, $vinicius->id, $rodrygo->id,
        ];

        $response = $this->actingAs($this->user)->postJson(
            route('game.lineup.computeSlots', $this->game->id),
            [
                'formation' => '4-3-3',
                'player_ids' => $playerIds,
                'manual_assignments' => [
                    '8' => $mbappe->id, // LW pin
                ],
            ],
        );

        $response->assertOk();
        $map = $response->json('slot_assignments');

        $this->assertSame($mbappe->id, $map['8'], 'Mbappé must stay pinned to LW');
        // Without the pin, Mbappé would have landed at CF — somebody else must.
        $this->assertNotSame($mbappe->id, $map['9']);
        $this->assertContains($map['9'], [$vinicius->id, $rodrygo->id]);
    }
}
