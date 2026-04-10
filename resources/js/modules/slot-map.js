/**
 * Mechanical helpers that operate on a flat {slotId: playerId} map.
 *
 * This module deliberately holds **zero placement logic**. The authoritative
 * slot-assignment algorithm lives on the PHP side (FormationRecommender).
 * The frontend only needs three things:
 *
 *   1. A reactive slot map it can mutate directly for explicit user actions
 *      (drag-drop swap, click-to-assign, add, remove).
 *   2. A cheap helper to place a newly-added player in the first empty slot
 *      matching their primary position — instant, no network round trip.
 *   3. A cheap helper to turn the flat map into the list-of-slot-objects
 *      shape the pitch renderer expects.
 *
 * For anything smarter than that (formation change, "Auto" button), the
 * frontend calls the backend `game.lineup.computeSlots` endpoint and replaces
 * its local map with the response.
 */

/**
 * Natural slot label for a canonical player position — the 100-compat cells
 * of PositionSlotMapper::SLOT_COMPATIBILITY in PHP. Kept deliberately tiny
 * and used only by `placeInFirstMatchingSlot` for local add-player UX.
 */
const PRIMARY_TO_SLOT = {
    'Goalkeeper': 'GK',
    'Centre-Back': 'CB',
    'Left-Back': 'LB',
    'Right-Back': 'RB',
    'Defensive Midfield': 'DM',
    'Central Midfield': 'CM',
    'Attacking Midfield': 'AM',
    'Left Midfield': 'LM',
    'Right Midfield': 'RM',
    'Left Winger': 'LW',
    'Right Winger': 'RW',
    'Centre-Forward': 'CF',
    'Second Striker': 'CF',
};

/**
 * Return a new slot map with two entries swapped. If either slot is empty
 * the occupied slot's player simply moves across and the previously-occupied
 * slot becomes empty.
 */
export function swapSlots(map, slotIdA, slotIdB) {
    const a = map[slotIdA] ?? null;
    const b = map[slotIdB] ?? null;
    const next = { ...map };

    if (b !== null) {
        next[slotIdA] = b;
    } else {
        delete next[slotIdA];
    }
    if (a !== null) {
        next[slotIdB] = a;
    } else {
        delete next[slotIdB];
    }
    return next;
}

/**
 * Return a new slot map with `player` placed in the first empty slot whose
 * label matches their primary position. Falls back to any empty slot if no
 * primary match exists. Returns the original map unchanged if every slot is
 * already full.
 */
export function placeInFirstMatchingSlot(map, slots, player) {
    if (!player) return map;

    const next = { ...map };
    const primaryLabel = PRIMARY_TO_SLOT[player.position] ?? null;

    if (primaryLabel) {
        for (const slot of slots) {
            if (slot.label === primaryLabel && !next[slot.id]) {
                next[slot.id] = player.id;
                return next;
            }
        }
    }

    for (const slot of slots) {
        if (!next[slot.id]) {
            next[slot.id] = player.id;
            return next;
        }
    }

    return next;
}

/**
 * Return a new slot map with the given player removed from wherever they
 * currently sit. No-op if they aren't in the map.
 */
export function removePlayer(map, playerId) {
    const next = { ...map };
    for (const slotId of Object.keys(next)) {
        if (next[slotId] === playerId) {
            delete next[slotId];
        }
    }
    return next;
}

/**
 * Apply a substitution: the incoming player takes the outgoing player's slot.
 * Used by the live-match view when a sub is confirmed. Returns a new map.
 */
export function applySubstitution(map, outgoingPlayerId, incomingPlayerId) {
    const next = { ...map };
    for (const slotId of Object.keys(next)) {
        if (next[slotId] === outgoingPlayerId) {
            next[slotId] = incomingPlayerId;
            return next;
        }
    }
    return next;
}

/**
 * Turn a flat {slotId: playerId} map into the [{slot, player, compatibility, isManual}]
 * array the pitch renderer consumes. `compatibilityMatrix` is optional — when
 * provided, each slot gets a display-only compat score (used for badges /
 * tooltips). Slots with no assigned player get a null player.
 */
export function buildSlotView(map, slots, playersById, compatibilityMatrix = null) {
    return slots.map(slot => {
        const playerId = map[slot.id] ?? null;
        const player = playerId ? playersById[playerId] : null;

        let compatibility = 0;
        if (player && compatibilityMatrix) {
            compatibility = getPlayerCompatibility(player, slot.label, compatibilityMatrix);
        }

        return {
            ...slot,
            player: player ? { ...player, compatibility } : null,
            compatibility,
            // `isManual` is vestigial — the old algorithm distinguished auto
            // vs manual placements, but the new flow has no such distinction.
            // Kept on the shape so existing Blade templates keep type-matching.
            isManual: false,
        };
    });
}

/**
 * Reconcile a slot map against the authoritative `selectedPlayerIds` list:
 *
 *   1. Prune ghost entries whose value is no longer in `selectedPlayerIds`
 *      (stale state from legacy DB rows, preset mismatches, etc.).
 *   2. Force-place any orphaned selected players — i.e. ids present in
 *      `selectedPlayerIds` but not yet on any slot. Tries primary-match
 *      first, then the first empty slot regardless of label. If every slot
 *      is already full (shouldn't happen, but be defensive), the orphan is
 *      left out — we prefer losing one player to crashing the view.
 *
 * This is the frontend mirror of `FormationRecommender::fillByForceAssignment`:
 * a defense-in-depth guarantee that "11 selected ⇒ 11 on pitch" no matter
 * what the backend returned or what the preset contained.
 *
 * Returns a new map — does not mutate the input.
 *
 * @param {Object} map - current { slotId: playerId }
 * @param {string[]} selectedPlayerIds - the authoritative selection list
 * @param {Array} slots - formation slots, in pitch order
 * @param {Object} [playersById] - optional lookup, only needed for primary matching
 * @returns {Object} new slot map
 */
export function normalizeSlotMap(map, selectedPlayerIds, slots, playersById = {}) {
    const selectedSet = new Set(selectedPlayerIds);

    // Step 1: prune ghost entries (map values not in selectedSet).
    let next = {};
    for (const slotId of Object.keys(map)) {
        const playerId = map[slotId];
        if (selectedSet.has(playerId)) {
            next[slotId] = playerId;
        }
    }

    // Step 2: find orphans — selected ids not present in the map.
    const placedIds = new Set(Object.values(next));
    const orphans = selectedPlayerIds.filter(id => !placedIds.has(id));

    for (const orphanId of orphans) {
        const player = playersById[orphanId] ?? { id: orphanId, position: null };
        // First try the shared primary-match helper.
        const afterPrimary = placeInFirstMatchingSlot(next, slots, player);
        if (afterPrimary !== next) {
            next = afterPrimary;
            continue;
        }
        // Fallback: force-fill the first empty slot regardless of label.
        // placeInFirstMatchingSlot only returns the original map if every
        // slot is full — at that point we've run out of pitch.
        let placed = false;
        for (const slot of slots) {
            if (!next[slot.id]) {
                next = { ...next, [slot.id]: orphanId };
                placed = true;
                break;
            }
        }
        if (!placed) {
            // Every slot is occupied. This should never happen if
            // selectedPlayerIds.length <= slots.length. Log and move on.
            console.warn('normalizeSlotMap: no empty slot for orphan', orphanId);
        }
    }

    return next;
}

/**
 * Best-of compat score across a player's primary + secondary positions for a
 * given slot. Used for display-only badges. Moved here from the deleted
 * `slot-assignment.js` so the pitch can still show "Natural / Good / Poor"
 * tooltips without a round trip.
 */
export function getPlayerCompatibility(player, slotLabel, slotCompatibility) {
    let best = 0;
    for (const pos of (player.positions || [player.position])) {
        const score = slotCompatibility[slotLabel]?.[pos] ?? 0;
        if (score > best) best = score;
    }
    return best;
}
