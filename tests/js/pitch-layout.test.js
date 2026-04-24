/**
 * Regression: when the user stages a pending substitution (no formation
 * change, no drag-swap), the pitch preview must not shuffle unrelated
 * players out of the slots the server will keep them in.
 *
 * Before the fix, selectSlotMap ran bestFitPlacement against the active XI
 * with pins sourced ONLY from _manualSlotPins — so a plain sub caused the
 * preview to re-solve all 11 slots from scratch and players unrelated to
 * the sub visibly moved. The committed result (from the server) was
 * correct; only the interim staging UI reshuffled.
 *
 * The frontend fix mirrors TacticalChangeService::processLiveMatchChanges:
 * seed manualPins with every staying player's current slot from
 * startingSlotMap, then let drag-swap pins overlay. A natural sub then
 * only rewrites the outgoing player's slot.
 */
import { describe, it, expect } from 'vitest';
import { createPitchLayout } from '@/modules/pitch-layout.js';

// Minimal 4-3-3 slot set; ids match Formation::F_4_3_3->pitchSlots() in PHP.
const F_4_3_3_SLOTS = [
    { id: 0, role: 'Goalkeeper', col: 4, row: 0, label: 'GK' },
    { id: 1, role: 'Defender', col: 1, row: 3, label: 'LB' },
    { id: 2, role: 'Defender', col: 3, row: 3, label: 'CB' },
    { id: 3, role: 'Defender', col: 5, row: 3, label: 'CB' },
    { id: 4, role: 'Defender', col: 7, row: 3, label: 'RB' },
    { id: 5, role: 'Midfielder', col: 2, row: 7, label: 'CM' },
    { id: 6, role: 'Midfielder', col: 4, row: 7, label: 'CM' },
    { id: 7, role: 'Midfielder', col: 6, row: 7, label: 'CM' },
    { id: 8, role: 'Forward', col: 1, row: 10, label: 'LW' },
    { id: 9, role: 'Forward', col: 4, row: 11, label: 'CF' },
    { id: 10, role: 'Forward', col: 7, row: 10, label: 'RW' },
];

// Compatibility matrix excerpt — only the labels and positions this test uses.
const SLOT_COMPATIBILITY = {
    GK: { Goalkeeper: 100 },
    CB: { 'Centre-Back': 100, 'Defensive Midfield': 80, 'Left-Back': 40, 'Right-Back': 40 },
    LB: { 'Left-Back': 100, 'Left Midfield': 80, 'Left Winger': 40, 'Centre-Back': 40, 'Right-Back': 30 },
    RB: { 'Right-Back': 100, 'Right Midfield': 80, 'Right Winger': 40, 'Centre-Back': 40, 'Left-Back': 30 },
    CM: {
        'Central Midfield': 100,
        'Defensive Midfield': 80,
        'Attacking Midfield': 80,
        'Left Midfield': 80,
        'Right Midfield': 80,
    },
    LW: {
        'Left Winger': 100,
        'Left Midfield': 80,
        'Second Striker': 50,
        'Right Winger': 50,
        'Centre-Forward': 80,
        'Left-Back': 20,
    },
    RW: {
        'Right Winger': 100,
        'Right Midfield': 80,
        'Second Striker': 50,
        'Left Winger': 50,
        'Centre-Forward': 80,
        'Right-Back': 20,
    },
    CF: {
        'Centre-Forward': 100,
        'Second Striker': 100,
        'Left Winger': 80,
        'Right Winger': 80,
        'Attacking Midfield': 40,
    },
};

function p(id, position, secondaryPositions = []) {
    return {
        id,
        name: id,
        position,
        positions: [position, ...secondaryPositions],
        secondary_positions: secondaryPositions,
        overallScore: 70,
    };
}

function makeCtx({ startingSlotMap, lineupPlayers, benchPlayers, pendingSubs = [], manualSlotPins = {} }) {
    return {
        lineupPlayers,
        benchPlayers,
        pendingSubs,
        substitutionsMade: [],
        selectedPlayerOut: null,
        selectedPlayerIn: null,
        redCardedPlayerIds: [],
        activeFormation: '4-3-3',
        pendingFormation: null,
        _pitchPositionsFormation: '4-3-3',
        formationSlots: { '4-3-3': F_4_3_3_SLOTS },
        slotCompatibility: SLOT_COMPATIBILITY,
        startingSlotMap,
        _manualSlotPins: manualSlotPins,
        getActiveLineupPlayers() { return [...this.lineupPlayers]; },
    };
}

// Builds the same post-drag-swap state used by the backend regression test
// (tests/Feature/SubstitutionSlotPreservationTest.php): Player A is a
// versatile RB/CM placed via his CM secondary at slot 6; Player B is a pure
// CM drag-swapped into slot 4 (an out-of-position RB).
function setupPostDragSwapScenario() {
    const gk = p('gk', 'Goalkeeper');
    const lb = p('lb', 'Left-Back');
    const cb1 = p('cb1', 'Centre-Back');
    const cb2 = p('cb2', 'Centre-Back');
    const playerA = p('A', 'Right-Back', ['Central Midfield']);
    const cm2 = p('cm2', 'Central Midfield');
    const playerB = p('B', 'Central Midfield');
    const cm3 = p('cm3', 'Central Midfield');
    const lw = p('lw', 'Left Winger');
    const cf = p('cf', 'Centre-Forward');
    const rw = p('rw', 'Right Winger');

    // Bench: a natural RB replacement for Player B.
    const playerC = p('C', 'Right-Back');

    return {
        lineupPlayers: [gk, lb, cb1, cb2, playerA, cm2, playerB, cm3, lw, cf, rw],
        benchPlayers: [playerC],
        startingSlotMap: {
            0: gk.id,
            1: lb.id,
            2: cb1.id,
            3: cb2.id,
            4: playerB.id,   // RB slot ← B (non-natural, drag-swapped in)
            5: cm2.id,
            6: playerA.id,   // CM slot ← A (drag-swapped in via CM secondary)
            7: cm3.id,
            8: lw.id,
            9: cf.id,
            10: rw.id,
        },
        players: { gk, lb, cb1, cb2, playerA, cm2, playerB, cm3, lw, cf, rw, playerC },
    };
}

describe('pitch-layout slotAssignments', () => {
    it('returns startingSlotMap verbatim when there are no pending subs', () => {
        const scenario = setupPostDragSwapScenario();
        const ctx = makeCtx({ ...scenario, pendingSubs: [] });
        const layout = createPitchLayout(() => ctx);

        const assignments = layout.slotAssignments;
        const mapById = Object.fromEntries(
            assignments.map(a => [a.id, a.player?.id ?? null]),
        );

        for (const [slotId, playerId] of Object.entries(scenario.startingSlotMap)) {
            expect(mapById[slotId]).toBe(playerId);
        }
    });

    it('does not move unrelated starters when staging a natural sub', () => {
        // This is the fix's regression: a plain sub (B out, C in) must keep
        // Player A at CM and every other starter exactly where startingSlotMap
        // says they are. Only the outgoing slot (RB) is allowed to change.
        const scenario = setupPostDragSwapScenario();
        const { playerB, playerC, playerA } = scenario.players;

        const ctx = makeCtx({
            ...scenario,
            pendingSubs: [{ playerOut: playerB, playerIn: playerC }],
        });
        const layout = createPitchLayout(() => ctx);

        const assignments = layout.slotAssignments;
        const mapById = Object.fromEntries(
            assignments.map(a => [a.id, a.player?.id ?? null]),
        );

        // Incoming natural RB takes the outgoing player's slot.
        expect(mapById[4]).toBe(playerC.id);

        // Player A stays at the CM slot the user chose via drag-swap —
        // without the pin-preservation fix, A would slide back to his
        // primary RB slot and C would be pushed out of position.
        expect(mapById[6]).toBe(playerA.id);

        // Every other starter is untouched.
        const unchangedSlots = [0, 1, 2, 3, 5, 7, 8, 9, 10];
        for (const slotId of unchangedSlots) {
            expect(mapById[slotId]).toBe(scenario.startingSlotMap[slotId]);
        }
    });

    it('marks the incoming player with isPendingSub so the pitch can style it', () => {
        const scenario = setupPostDragSwapScenario();
        const { playerB, playerC } = scenario.players;

        const ctx = makeCtx({
            ...scenario,
            pendingSubs: [{ playerOut: playerB, playerIn: playerC }],
        });
        const layout = createPitchLayout(() => ctx);

        const incoming = layout.slotAssignments.find(a => a.player?.id === playerC.id);
        expect(incoming).toBeDefined();
        expect(incoming.player.isPendingSub).toBe(true);

        // Staying players must not be flagged as pending.
        const staying = layout.slotAssignments.filter(
            a => a.player && a.player.id !== playerC.id,
        );
        for (const row of staying) {
            expect(row.player.isPendingSub).toBeUndefined();
        }
    });

    it('keeps a natural sub in the outgoing slot even when the incoming player is not the best primary fit', () => {
        // Scenario: outgoing is a CM (slot 6), incoming is a Right-Winger
        // with no CM-compatible positions. Without pinning, bestFitPlacement
        // would re-solve the XI from scratch and could scatter unrelated
        // players. With the fix, the 10 staying players stay pinned and the
        // RW lands in the (only empty) CM slot as a poor fit — mirroring the
        // backend's behavior.
        const gk = p('gk', 'Goalkeeper');
        const lb = p('lb', 'Left-Back');
        const cb1 = p('cb1', 'Centre-Back');
        const cb2 = p('cb2', 'Centre-Back');
        const rb = p('rb', 'Right-Back');
        const cm1 = p('cm1', 'Central Midfield');
        const cmOut = p('cmOut', 'Central Midfield');
        const cm3 = p('cm3', 'Central Midfield');
        const lw = p('lw', 'Left Winger');
        const cf = p('cf', 'Centre-Forward');
        const rw = p('rw', 'Right Winger');
        const rwBench = p('rwBench', 'Right Winger');

        const startingSlotMap = {
            0: gk.id, 1: lb.id, 2: cb1.id, 3: cb2.id, 4: rb.id,
            5: cm1.id, 6: cmOut.id, 7: cm3.id,
            8: lw.id, 9: cf.id, 10: rw.id,
        };

        const ctx = makeCtx({
            lineupPlayers: [gk, lb, cb1, cb2, rb, cm1, cmOut, cm3, lw, cf, rw],
            benchPlayers: [rwBench],
            startingSlotMap,
            pendingSubs: [{ playerOut: cmOut, playerIn: rwBench }],
        });
        const layout = createPitchLayout(() => ctx);
        const mapById = Object.fromEntries(
            layout.slotAssignments.map(a => [a.id, a.player?.id ?? null]),
        );

        // Incoming RW lands in the vacated CM slot (it's the only empty one).
        expect(mapById[6]).toBe(rwBench.id);

        // No staying player has been moved to a different slot.
        const unchangedSlots = [0, 1, 2, 3, 4, 5, 7, 8, 9, 10];
        for (const slotId of unchangedSlots) {
            expect(mapById[slotId]).toBe(startingSlotMap[slotId]);
        }
    });

    it('honors drag-swap pins over startingSlotMap when a pending sub is staged', () => {
        // If the user drag-swaps BEFORE staging a sub, _manualSlotPins
        // already reflects the new intent. Drag-swap pins must win over
        // startingSlotMap (defensive precedence check; in practice the
        // drag-swap handler keeps both in sync).
        const gk = p('gk', 'Goalkeeper');
        const lb = p('lb', 'Left-Back');
        const cb1 = p('cb1', 'Centre-Back');
        const cb2 = p('cb2', 'Centre-Back');
        const rb = p('rb', 'Right-Back');
        const cm1 = p('cm1', 'Central Midfield');
        const cm2 = p('cm2', 'Central Midfield');
        const cm3 = p('cm3', 'Central Midfield');
        const lw = p('lw', 'Left Winger');
        const cf = p('cf', 'Centre-Forward');
        const rw = p('rw', 'Right Winger');
        const cfBench = p('cfBench', 'Centre-Forward');

        // startingSlotMap has CM1 at slot 5, CM2 at slot 6 — pre-swap state.
        // _manualSlotPins reflects a drag-swap putting CM2 at 5 and CM1 at 6.
        const startingSlotMap = {
            0: gk.id, 1: lb.id, 2: cb1.id, 3: cb2.id, 4: rb.id,
            5: cm1.id, 6: cm2.id, 7: cm3.id,
            8: lw.id, 9: cf.id, 10: rw.id,
        };
        const manualSlotPins = {
            5: cm2.id,
            6: cm1.id,
        };

        const ctx = makeCtx({
            lineupPlayers: [gk, lb, cb1, cb2, rb, cm1, cm2, cm3, lw, cf, rw],
            benchPlayers: [cfBench],
            startingSlotMap,
            manualSlotPins,
            // Sub CF out, CF-bench in — unrelated to the drag-swap slots.
            pendingSubs: [{ playerOut: cf, playerIn: cfBench }],
        });
        const layout = createPitchLayout(() => ctx);
        const mapById = Object.fromEntries(
            layout.slotAssignments.map(a => [a.id, a.player?.id ?? null]),
        );

        // Drag-swap pins win: CM2 at 5, CM1 at 6 (not the startingSlotMap order).
        expect(mapById[5]).toBe(cm2.id);
        expect(mapById[6]).toBe(cm1.id);

        // The sub plays out in the CF slot (9), no one else moves.
        expect(mapById[9]).toBe(cfBench.id);
        expect(mapById[0]).toBe(gk.id);
        expect(mapById[1]).toBe(lb.id);
        expect(mapById[8]).toBe(lw.id);
        expect(mapById[10]).toBe(rw.id);
    });
});
