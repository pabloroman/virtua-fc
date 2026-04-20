/**
 * Live-match pitch layout composition. Exposes `currentPitchSlots`,
 * `currentSlots`, and `slotAssignments` as Alpine getters that read
 * component state (lineup, pending subs, preview map, drag pins) and
 * delegate placement math to pure helpers.
 *
 * The slotAssignments getter is broken into:
 *   - composeActiveXI(): apply pending subs to the on-pitch lineup
 *   - selectSlotMap(): pick the right source map (preview / startingSlotMap /
 *     reshuffle via bestFit) based on formation preview + pending-sub state
 *   - fixupGoalkeeperSlot(): swap in a reserve GK when the starter is sent off
 *     and bestFit didn't place another goalkeeper in the GK slot.
 */
import { bestFitPlacement, buildSlotView, getPlayerCompatibility } from './slot-map.js';

export function createPitchLayout(ctx) {
    function composeActiveXI(playersById) {
        const c = ctx();

        const allPendingSubs = [...c.pendingSubs];
        if (c.selectedPlayerOut && c.selectedPlayerIn) {
            const alreadyPending = allPendingSubs.some(s => s.playerOut.id === c.selectedPlayerOut.id);
            if (!alreadyPending) {
                allPendingSubs.push({ playerOut: c.selectedPlayerOut, playerIn: c.selectedPlayerIn });
            }
        }

        const pendingOutIds = new Set(allPendingSubs.map(s => s.playerOut.id));
        const pendingInIds = new Set(allPendingSubs.map(s => s.playerIn.id));

        const activePlayers = c.getActiveLineupPlayers().filter(p => !pendingOutIds.has(p.id));
        for (const sub of allPendingSubs) {
            const inPlayer = playersById[sub.playerIn.id] ?? sub.playerIn;
            if (inPlayer) activePlayers.push(inPlayer);
        }

        return { allPendingSubs, pendingOutIds, pendingInIds, activePlayers };
    }

    function selectSlotMap({ isFormationPreview, allPendingSubs, pendingOutIds, activePlayers, slots }) {
        const c = ctx();

        if (isFormationPreview) {
            if (c.previewSlotMap) return { ...c.previewSlotMap };
            // Backend fetch in flight — best-fit locally so the pitch isn't empty.
            // The authoritative map snaps in once refreshFormationPreview() resolves.
            return bestFitPlacement(activePlayers, slots, c.slotCompatibility);
        }

        if (allPendingSubs.length === 0) {
            // No pending subs — the saved startingSlotMap IS the authoritative
            // map (server rebuilt it on the last Apply). Honor it as-is,
            // including any in-match drag swaps the user applied on top.
            return { ...c.startingSlotMap };
        }

        // Pending subs present — reshuffle against the active XI within the
        // current formation, preserving the user's manual drag-swap intent.
        const manualPins = {};
        for (const [slotId, playerId] of Object.entries(c._manualSlotPins)) {
            if (pendingOutIds.has(playerId)) continue;
            if (!activePlayers.some(p => p.id === playerId)) continue;
            manualPins[slotId] = playerId;
        }
        return bestFitPlacement(activePlayers, slots, c.slotCompatibility, manualPins);
    }

    function fixupGoalkeeperSlot(assignments) {
        const c = ctx();
        const redCarded = c.redCardedPlayerIds;
        const gkSlot = assignments.find(s => s.role === 'Goalkeeper');
        if (!gkSlot?.player || !redCarded.includes(gkSlot.player.id)) return;

        const reserveGkSlot = assignments.find(s =>
            s !== gkSlot
            && s.player
            && s.player.position === 'Goalkeeper'
            && !redCarded.includes(s.player.id),
        );
        if (!reserveGkSlot) return;

        const temp = gkSlot.player;
        gkSlot.player = reserveGkSlot.player;
        reserveGkSlot.player = temp;
        gkSlot.compatibility = getPlayerCompatibility(gkSlot.player, gkSlot.label, c.slotCompatibility);
        reserveGkSlot.compatibility = getPlayerCompatibility(reserveGkSlot.player, reserveGkSlot.label, c.slotCompatibility);
    }

    return {
        get currentPitchSlots() {
            const c = ctx();
            const formation = c.pendingFormation ?? c.activeFormation;
            return c.formationSlots[formation] || [];
        },

        // Alias for pitch-display component compatibility (lineup uses currentSlots).
        get currentSlots() {
            return this.currentPitchSlots;
        },

        get slotAssignments() {
            const c = ctx();
            const effectiveFormation = c.pendingFormation ?? c.activeFormation;
            const isFormationPreview = effectiveFormation !== c._pitchPositionsFormation;
            const slots = this.currentPitchSlots;

            const playersById = {};
            for (const p of c.lineupPlayers || []) playersById[p.id] = p;
            for (const p of c.benchPlayers || []) playersById[p.id] = p;

            const { allPendingSubs, pendingOutIds, pendingInIds, activePlayers } = composeActiveXI(playersById);

            const map = selectSlotMap({ isFormationPreview, allPendingSubs, pendingOutIds, activePlayers, slots });

            const assignments = buildSlotView(map, slots, playersById, c.slotCompatibility);

            // Mark pending-sub players with isPendingSub so the pitch can
            // style them (dashed border, muted colors) without committing the sub.
            for (const row of assignments) {
                if (row.player && pendingInIds.has(row.player.id)) {
                    row.player = { ...row.player, isPendingSub: true };
                }
            }

            fixupGoalkeeperSlot(assignments);

            return assignments;
        },
    };
}
