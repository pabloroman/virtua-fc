/**
 * Get per-player compatibility score for a specific slot.
 *
 * Iterates all positions the player can play (player.positions) and returns
 * the best compatibility score. Falls back to player.position if positions
 * array is not available.
 *
 * @param {Object} player - Player data with positions array
 * @param {string} slotLabel - The slot code (e.g., 'CB', 'DM', 'CM')
 * @param {Object} slotCompatibility - Compatibility matrix { slotLabel: { position: score } }
 * @returns {number} Best compatibility score across all positions
 */
export function getPlayerCompatibility(player, slotLabel, slotCompatibility) {
    let best = 0;
    for (const pos of (player.positions || [player.position])) {
        const score = slotCompatibility[slotLabel]?.[pos] ?? 0;
        if (score > best) best = score;
    }
    return best;
}

/**
 * Unified player-to-slot assignment logic.
 *
 * Used by both the lineup page and live match to map players onto formation
 * slots. Honors manual assignments first, then auto-assigns remaining players
 * using a weighted score of player rating and positional compatibility.
 *
 * @param {Array} slots - Formation slots (with id, role, label). Will be mutated with player assignments.
 * @param {Array} players - Player data objects with id, position, overallScore
 * @param {Object} slotCompatibility - Compatibility matrix { slotLabel: { position: score } }
 * @param {Object} [manualAssignments={}] - Manual slot→player mappings { slotId: playerId }
 * @returns {Array} Mutated slots with player assignments
 */
export function assignPlayersToSlots(slots, players, slotCompatibility, manualAssignments = {}) {
    const assigned = new Set();

    // First: honor manual assignments
    for (const [slotId, playerId] of Object.entries(manualAssignments)) {
        const slot = slots.find(s => s.id === parseInt(slotId));
        const player = players.find(p => p.id === playerId);
        if (slot && player && !assigned.has(player.id)) {
            const compatibility = getPlayerCompatibility(player, slot.label, slotCompatibility);
            slot.player = { ...player, compatibility };
            slot.compatibility = compatibility;
            slot.isManual = true;
            assigned.add(player.id);
        }
    }

    // Auto-assign remaining players to remaining slots
    const emptySlots = slots.filter(s => !s.player);
    const unassignedPlayers = players.filter(p => !assigned.has(p.id));

    if (emptySlots.length > 0 && unassignedPlayers.length > 0) {
        // Pass 0: Reserve natural position fits first (compatibility 100)
        // This prevents suboptimal cascading where a natural RW takes an RW slot,
        // forcing an RM into CM instead of the now-vacant RW.
        const naturalCandidates = [...unassignedPlayers]
            .filter(p => !assigned.has(p.id))
            .sort((a, b) => b.overallScore - a.overallScore);

        naturalCandidates.forEach(player => {
            if (assigned.has(player.id)) return;
            const naturalSlot = slots.find(s =>
                !s.player &&
                getPlayerCompatibility(player, s.label, slotCompatibility) === 100
            );
            if (naturalSlot) {
                naturalSlot.player = { ...player, compatibility: 100 };
                naturalSlot.compatibility = 100;
                assigned.add(player.id);
            }
        });

        const rolePriority = { 'Goalkeeper': 0, 'Forward': 1, 'Defender': 2, 'Midfielder': 3 };
        const remainingEmpty = slots.filter(s => !s.player);
        const sortedEmpty = [...remainingEmpty].sort((a, b) => {
            const aPriority = rolePriority[a.role] ?? 99;
            const bPriority = rolePriority[b.role] ?? 99;
            if (aPriority !== bPriority) return aPriority - bPriority;
            const aCompat = Object.keys(slotCompatibility[a.label] || {}).length;
            const bCompat = Object.keys(slotCompatibility[b.label] || {}).length;
            return aCompat - bCompat;
        });

        // First pass: assign players with acceptable compatibility (>= 40)
        sortedEmpty.forEach(slot => {
            let bestPlayer = null;
            let bestScore = -1;

            unassignedPlayers.forEach(player => {
                if (assigned.has(player.id)) return;
                const compatibility = getPlayerCompatibility(player, slot.label, slotCompatibility);
                if (compatibility < 40) return;
                const weightedScore = (player.overallScore * 0.7) + (compatibility * 0.3);
                if (weightedScore > bestScore) {
                    bestScore = weightedScore;
                    bestPlayer = { ...player, compatibility };
                }
            });

            const originalSlot = slots.find(s => s.id === slot.id);
            if (originalSlot && bestPlayer) {
                originalSlot.player = bestPlayer;
                originalSlot.compatibility = bestPlayer.compatibility;
                assigned.add(bestPlayer.id);
            }
        });

        // Second pass: fill remaining empty slots with leftover players
        const stillEmpty = slots.filter(s => !s.player);
        const stillUnassigned = unassignedPlayers.filter(p => !assigned.has(p.id));

        stillEmpty.forEach((slot, index) => {
            if (stillUnassigned[index]) {
                const player = stillUnassigned[index];
                const compatibility = getPlayerCompatibility(player, slot.label, slotCompatibility);
                slot.player = { ...player, compatibility };
                slot.compatibility = compatibility;
            }
        });
    }

    return slots;
}
