<?php

namespace Tests\Unit;

use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Services\FormationRecommender;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class FormationRecommenderTest extends TestCase
{
    private FormationRecommender $recommender;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recommender = new FormationRecommender();
    }

    /**
     * Build a lightweight player array. `bestXIFor` accepts both Eloquent
     * models and plain arrays thanks to its precomputePlayers helper.
     */
    private function player(
        string $id,
        string $position,
        int $overall = 70,
        ?array $secondary = null,
    ): array {
        return [
            'id' => $id,
            'name' => $id,
            'position' => $position,
            'secondary_positions' => $secondary,
            'overall_score' => $overall,
        ];
    }

    /**
     * Map the result array to [slotId => playerId|null] for easy assertions.
     */
    private function slotMap(array $bestXI): array
    {
        $map = [];
        foreach ($bestXI as $row) {
            $map[$row['slot']['id']] = $row['player']['id'] ?? null;
        }

        return $map;
    }

    public function test_vanilla_squad_places_all_11_players_in_natural_slots(): void
    {
        // 4-3-3: GK, LB, CB, CB, RB, CM, CM, CM, LW, CF, RW
        $players = collect([
            $this->player('gk', 'Goalkeeper'),
            $this->player('lb', 'Left-Back'),
            $this->player('cb1', 'Centre-Back'),
            $this->player('cb2', 'Centre-Back'),
            $this->player('rb', 'Right-Back'),
            $this->player('cm1', 'Central Midfield'),
            $this->player('cm2', 'Central Midfield'),
            $this->player('cm3', 'Central Midfield'),
            $this->player('lw', 'Left Winger'),
            $this->player('cf', 'Centre-Forward'),
            $this->player('rw', 'Right Winger'),
        ]);

        $bestXI = $this->recommender->bestXIFor(Formation::F_4_3_3, $players);

        $this->assertCount(11, $bestXI);
        foreach ($bestXI as $row) {
            $this->assertNotNull($row['player'], "Slot {$row['slot']['label']} should be filled");
            $this->assertSame(
                100,
                $row['compatibility'],
                "Slot {$row['slot']['label']} should have natural compatibility",
            );
        }
    }

    public function test_swap_moves_versatile_player_to_cover_empty_wing(): void
    {
        // The bug scenario in a 4-3-3:
        //
        //   - Player A: primary LW / secondary RW, rating 80 (versatile, highest-rated LW)
        //   - Player B: primary LW only, rating 70 (specialist, lower-rated)
        //   - No unused player has Right Winger as primary or secondary.
        //
        // Naive greedy: A → LW (compat 100), RW empty, B unused.
        // Expected: B → LW (primary), A → RW (via secondary swap).
        $players = collect([
            $this->player('gk', 'Goalkeeper'),
            $this->player('lb', 'Left-Back'),
            $this->player('cb1', 'Centre-Back'),
            $this->player('cb2', 'Centre-Back'),
            $this->player('rb', 'Right-Back'),
            $this->player('cm1', 'Central Midfield'),
            $this->player('cm2', 'Central Midfield'),
            $this->player('cm3', 'Central Midfield'),
            $this->player('cf', 'Centre-Forward'),
            $this->player('lwVersatile', 'Left Winger', 80, ['Right Winger']),
            $this->player('lwSpecialist', 'Left Winger', 70),
        ]);

        $bestXI = $this->recommender->bestXIFor(Formation::F_4_3_3, $players);
        $map = $this->slotMap($bestXI);

        // Slot ids in F_4_3_3: 8 = LW, 9 = CF, 10 = RW
        $this->assertSame('lwSpecialist', $map[8], 'LW should go to the less versatile specialist');
        $this->assertSame('lwVersatile', $map[10], 'Versatile LW should cover RW via secondary');
        $this->assertSame('cf', $map[9]);

        // All 11 slots filled at compatibility 100.
        foreach ($bestXI as $row) {
            $this->assertNotNull($row['player'], "Slot {$row['slot']['label']} should be filled");
            $this->assertSame(100, $row['compatibility']);
        }
    }

    public function test_swap_fills_dm_using_cb_secondary_freeing_less_versatile_cb(): void
    {
        // 4-1-4-1 has 2 CBs and 1 DM. Squad has no primary DM:
        //   - cbTop (85): Centre-Back only
        //   - cbVersatile (80): Centre-Back / secondary Defensive Midfield
        //   - cbSpecialist (75): Centre-Back only
        //
        // Pass 1 fills 2 CB slots with cbTop + cbVersatile (rating DESC).
        // DM is empty; no unused player has DM as primary/secondary.
        // Pass 3 swaps: cbVersatile → DM, cbSpecialist → CB.
        $players = collect([
            $this->player('gk', 'Goalkeeper'),
            $this->player('lb', 'Left-Back'),
            $this->player('rb', 'Right-Back'),
            $this->player('lw', 'Left Winger'),
            $this->player('cm1', 'Central Midfield'),
            $this->player('cm2', 'Central Midfield'),
            $this->player('rw', 'Right Winger'),
            $this->player('cf', 'Centre-Forward'),
            $this->player('cbTop', 'Centre-Back', 85),
            $this->player('cbVersatile', 'Centre-Back', 80, ['Defensive Midfield']),
            $this->player('cbSpecialist', 'Centre-Back', 75),
        ]);

        $bestXI = $this->recommender->bestXIFor(Formation::F_4_1_4_1, $players);
        $map = $this->slotMap($bestXI);

        // F_4_1_4_1 slot ids: 2 = CB, 3 = CB, 5 = DM
        $this->assertContains('cbTop', [$map[2], $map[3]]);
        $this->assertContains('cbSpecialist', [$map[2], $map[3]]);
        $this->assertNotContains('cbVersatile', [$map[2], $map[3]]);
        $this->assertSame('cbVersatile', $map[5], 'DM slot should be filled by the versatile CB via swap');

        foreach ($bestXI as $row) {
            $this->assertNotNull($row['player'], "Slot {$row['slot']['label']} should be filled");
            $this->assertSame(100, $row['compatibility']);
        }
    }

    public function test_weighted_fallback_fills_slot_with_no_natural_fit(): void
    {
        // 4-3-3 with no Centre-Forward or Second Striker in the squad. The CF
        // slot must still be filled via Pass 4 (weighted fallback) using the
        // best available non-natural compat. Only unused candidate is an
        // Attacking Midfield player (compat 40 for CF) — which is exactly what
        // the fallback should pick.
        $players = collect([
            $this->player('gk', 'Goalkeeper'),
            $this->player('lb', 'Left-Back'),
            $this->player('cb1', 'Centre-Back'),
            $this->player('cb2', 'Centre-Back'),
            $this->player('rb', 'Right-Back'),
            $this->player('cm1', 'Central Midfield'),
            $this->player('cm2', 'Central Midfield'),
            $this->player('cm3', 'Central Midfield'),
            $this->player('lw', 'Left Winger'),
            $this->player('rw', 'Right Winger'),
            $this->player('am', 'Attacking Midfield'),
        ]);

        $bestXI = $this->recommender->bestXIFor(Formation::F_4_3_3, $players);
        $map = $this->slotMap($bestXI);

        // Slot 9 is the CF slot in F_4_3_3.
        $this->assertSame('am', $map[9], 'CF should be filled via weighted fallback');

        $cfRow = collect($bestXI)->firstWhere('slot.id', 9);
        $this->assertSame(40, $cfRow['compatibility'], 'AM → CF has non-natural compat of 40');

        // All 11 slots should end up filled (no gaps).
        foreach ($bestXI as $row) {
            $this->assertNotNull($row['player'], "Slot {$row['slot']['label']} should be filled");
        }
    }

    public function test_secondary_fill_prefers_unused_player_over_swap(): void
    {
        // If Pass 2 can fill an empty slot with an unused player via his
        // secondary, we should not trigger a swap.
        $players = collect([
            $this->player('gk', 'Goalkeeper'),
            $this->player('lb', 'Left-Back'),
            $this->player('cb1', 'Centre-Back'),
            $this->player('cb2', 'Centre-Back'),
            $this->player('rb', 'Right-Back'),
            $this->player('cm1', 'Central Midfield'),
            $this->player('cm2', 'Central Midfield'),
            $this->player('lw', 'Left Winger'),
            $this->player('cf', 'Centre-Forward'),
            // RM primary with secondary Right Winger → compat 100 for RW.
            $this->player('extraRw', 'Right Midfield', 78, ['Right Winger']),
            $this->player('cm3', 'Central Midfield'),
        ]);

        $bestXI = $this->recommender->bestXIFor(Formation::F_4_3_3, $players);
        $map = $this->slotMap($bestXI);

        $this->assertSame('lw', $map[8], 'LW should stay where he is');
        $this->assertSame('cf', $map[9], 'CF should stay where he is');
        $this->assertSame('extraRw', $map[10], 'RW should be covered by secondary-RW player');
    }

    public function test_manual_pin_forces_player_into_specified_slot(): void
    {
        // Mbappé (primary Centre-Forward) would naturally land at CF, but the
        // user has explicitly pinned him to LW. Honor the pin, then fill the
        // rest of the XI around it — Vinicius should still land somewhere
        // sensible (CF or RW) without being pushed off the pitch.
        $players = collect([
            $this->player('gk', 'Goalkeeper'),
            $this->player('lb', 'Left-Back'),
            $this->player('cb1', 'Centre-Back'),
            $this->player('cb2', 'Centre-Back'),
            $this->player('rb', 'Right-Back'),
            $this->player('cm1', 'Central Midfield'),
            $this->player('cm2', 'Central Midfield'),
            $this->player('cm3', 'Central Midfield'),
            $this->player('mbappe', 'Centre-Forward', 92, ['Left Winger', 'Right Winger']),
            $this->player('vinicius', 'Left Winger', 88, ['Centre-Forward']),
            $this->player('rodrygo', 'Right Winger', 83, ['Left Winger', 'Centre-Forward']),
        ]);

        // Slot 8 is LW in F_4_3_3.
        $bestXI = $this->recommender->bestXIFor(Formation::F_4_3_3, $players, [8 => 'mbappe']);
        $map = $this->slotMap($bestXI);

        $this->assertSame('mbappe', $map[8], 'Mbappé must stay pinned to LW');
        $this->assertSame('rodrygo', $map[10], 'Rodrygo takes RW via his primary');
        $this->assertSame('vinicius', $map[9], 'Vinicius falls back to CF via his secondary');

        foreach ($bestXI as $row) {
            $this->assertNotNull($row['player'], "Slot {$row['slot']['label']} should be filled");
        }
    }

    public function test_manual_pin_ignores_invalid_entries(): void
    {
        // Invalid pins (unknown slot id, unknown player id) must be silently
        // dropped so the rest of the XI can still be computed.
        $players = collect([
            $this->player('gk', 'Goalkeeper'),
            $this->player('lb', 'Left-Back'),
            $this->player('cb1', 'Centre-Back'),
            $this->player('cb2', 'Centre-Back'),
            $this->player('rb', 'Right-Back'),
            $this->player('cm1', 'Central Midfield'),
            $this->player('cm2', 'Central Midfield'),
            $this->player('cm3', 'Central Midfield'),
            $this->player('lw', 'Left Winger'),
            $this->player('cf', 'Centre-Forward'),
            $this->player('rw', 'Right Winger'),
        ]);

        $bestXI = $this->recommender->bestXIFor(Formation::F_4_3_3, $players, [
            99 => 'lw',      // invalid slot id
            8 => 'ghost',    // invalid player id
        ]);

        // Everyone should still end up in their natural slot.
        $map = $this->slotMap($bestXI);
        $this->assertSame('lw', $map[8]);
        $this->assertSame('cf', $map[9]);
        $this->assertSame('rw', $map[10]);
    }

    public function test_switching_4_3_3_to_4_4_2_with_three_forwards_keeps_all_players_on_pitch(): void
    {
        // Exact user-reported regression: a 4-3-3 squad with three dedicated
        // forwards (LW, CF, RW) getting switched to 4-4-2 used to leave one
        // forward orphaned — fillByWeighted skipped them because their primary
        // had compat 0 for every remaining midfield slot.
        //
        // With the Pass 5 force-place pass, the leftover forward must end up
        // in *some* slot (ugly but on-pitch). The user can then drag them to
        // a sensible spot.
        $players = collect([
            $this->player('gk', 'Goalkeeper'),
            $this->player('lb', 'Left-Back'),
            $this->player('cb1', 'Centre-Back'),
            $this->player('cb2', 'Centre-Back'),
            $this->player('rb', 'Right-Back'),
            $this->player('cm1', 'Central Midfield'),
            $this->player('cm2', 'Central Midfield'),
            $this->player('cm3', 'Central Midfield'),
            $this->player('lw', 'Left Winger'),
            $this->player('cf', 'Centre-Forward'),
            $this->player('rw', 'Right Winger'),
        ]);

        $bestXI = $this->recommender->bestXIFor(Formation::F_4_4_2, $players);

        // Every player id in the squad must appear in the result — no orphans.
        $placedIds = collect($bestXI)->pluck('player.id')->filter()->values()->all();
        sort($placedIds);
        $expectedIds = $players->pluck('id')->sort()->values()->all();
        $this->assertSame($expectedIds, $placedIds, 'Every selected player must be on the pitch');

        // All 11 slots must have a player.
        foreach ($bestXI as $row) {
            $this->assertNotNull($row['player'], "Slot {$row['slot']['label']} should be filled");
        }
    }

    public function test_force_place_pass_saves_orphan_with_zero_compat(): void
    {
        // Construct a squad where one player's primary scores 0 for every
        // possible remaining slot once the other 10 are placed.
        //
        // 4-4-2 slots: GK, LB, CB, CB, RB, LM, CM, CM, RM, CF, CF.
        //
        // Squad: 1 GK, 4 defenders (2 CBs + LB + RB), 3 natural CFs (ratings
        // 90/85/80 — top two fill both CF slots), plus 3 CMs. Leaves the 3rd
        // CF (pure Centre-Forward, no secondaries) unable to go anywhere
        // because CF → {LM, CM, RM} is all 0 in the compat matrix.
        //
        // Pass 5 must still place the orphan somewhere. Compat 0 is expected.
        $players = collect([
            $this->player('gk', 'Goalkeeper', 75),
            $this->player('lb', 'Left-Back', 75),
            $this->player('cb1', 'Centre-Back', 75),
            $this->player('cb2', 'Centre-Back', 75),
            $this->player('rb', 'Right-Back', 75),
            $this->player('cm1', 'Central Midfield', 75),
            $this->player('cm2', 'Central Midfield', 75),
            $this->player('cm3', 'Central Midfield', 75),
            $this->player('cfTop', 'Centre-Forward', 90),
            $this->player('cfMid', 'Centre-Forward', 85),
            $this->player('cfOrphan', 'Centre-Forward', 80),
        ]);

        $bestXI = $this->recommender->bestXIFor(Formation::F_4_4_2, $players);
        $map = $this->slotMap($bestXI);

        // All 11 must be placed.
        $placedIds = array_filter(array_values($map));
        sort($placedIds);
        $expectedIds = $players->pluck('id')->sort()->values()->all();
        $this->assertSame($expectedIds, $placedIds, 'Orphan CF must end up on the pitch');

        // The orphan lands in some non-CF slot at compat 0.
        $orphanRow = collect($bestXI)->first(fn ($row) => ($row['player']['id'] ?? null) === 'cfOrphan');
        $this->assertNotNull($orphanRow);
        $this->assertSame(0, $orphanRow['compatibility'], 'Orphan placement must honestly report compat 0');
        $this->assertNotSame('CF', $orphanRow['slot']['label'], 'CF slots are already taken by higher-rated forwards');
    }

    public function test_getBestFormation_still_returns_a_formation_enum(): void
    {
        // Sanity: the legacy public method must keep working for its current
        // call sites (LineupService::selectAIFormation).
        $players = collect([
            $this->player('gk', 'Goalkeeper'),
            $this->player('lb', 'Left-Back'),
            $this->player('cb1', 'Centre-Back'),
            $this->player('cb2', 'Centre-Back'),
            $this->player('rb', 'Right-Back'),
            $this->player('cm1', 'Central Midfield'),
            $this->player('cm2', 'Central Midfield'),
            $this->player('cm3', 'Central Midfield'),
            $this->player('lw', 'Left Winger'),
            $this->player('cf', 'Centre-Forward'),
            $this->player('rw', 'Right Winger'),
        ]);

        $formation = $this->recommender->getBestFormation($players);
        $this->assertInstanceOf(Formation::class, $formation);
    }
}
