/**
 * Substitution manager module.
 *
 * Handles substitution validation, limits, window tracking, pending sub queue,
 * available player computation, and card tracking for the live match
 * tactical center.
 *
 * @param {Function} ctx - Returns the Alpine component instance
 */
export function createSubstitutionManager(ctx) {

    function getRedCardedPlayerIds() {
        const state = ctx();
        return state.revealedEvents
            .filter(e => e.type === 'red_card' && e.teamId === state.userTeamId)
            .map(e => e.gamePlayerId);
    }

    function getYellowCardedPlayerIds() {
        const state = ctx();
        return state.revealedEvents
            .filter(e => e.type === 'yellow_card' && e.teamId === state.userTeamId)
            .map(e => e.gamePlayerId);
    }

    function isPlayerYellowCarded(playerId) {
        return getYellowCardedPlayerIds().includes(playerId);
    }

    // Dynamic limits: 6 subs / 4 windows during ET in knockout, 5/3 otherwise
    function getEffectiveMaxSubstitutions() {
        const state = ctx();
        return (state.isKnockout && state.hasExtraTime) ? 6 : state.maxSubstitutions;
    }

    function getEffectiveMaxWindows() {
        const state = ctx();
        return (state.isKnockout && state.hasExtraTime) ? 4 : state.maxWindows;
    }

    function getWindowsUsed() {
        const minutes = new Set(ctx().substitutionsMade.map(s => s.minute));
        return minutes.size;
    }

    function getHasWindowsLeft() {
        return getWindowsUsed() < getEffectiveMaxWindows();
    }

    function getSubsRemaining() {
        const state = ctx();
        return getEffectiveMaxSubstitutions() - state.substitutionsMade.length - state.pendingSubs.length;
    }

    function getCanSubstitute() {
        const state = ctx();
        return state.substitutionsMade.length + state.pendingSubs.length < getEffectiveMaxSubstitutions();
    }

    function getCanAddMoreToPending() {
        return getCanSubstitute() && ctx().pendingSubs.length < getSubsRemaining();
    }

    // Lineup players considering both confirmed subs AND pending subs in this window
    function getAvailableLineupForPicker() {
        const state = ctx();
        const confirmedOutIds = state.substitutionsMade.map(s => s.playerOutId);
        const confirmedInIds = state.substitutionsMade.map(s => s.playerInId);
        const pendingOutIds = state.pendingSubs.map(s => s.playerOut.id);
        const pendingInIds = state.pendingSubs.map(s => s.playerIn.id);
        const allOutIds = [...confirmedOutIds, ...pendingOutIds];
        const allInIds = [...confirmedInIds, ...pendingInIds];
        const redCarded = getRedCardedPlayerIds();

        // Original lineup players still on pitch
        const onPitch = state.lineupPlayers.filter(p =>
            !allOutIds.includes(p.id) && !redCarded.includes(p.id)
        );

        // Players who came on (confirmed or pending) and are still on pitch
        const subsOnPitch = state.benchPlayers.filter(p =>
            allInIds.includes(p.id) && !allOutIds.includes(p.id) && !redCarded.includes(p.id)
        );

        return [...onPitch, ...subsOnPitch].sort((a, b) => a.positionSort - b.positionSort);
    }

    // Bench players minus those already subbed in (confirmed or pending)
    function getAvailableBenchForPicker() {
        const state = ctx();
        const confirmedInIds = state.substitutionsMade.map(s => s.playerInId);
        const pendingInIds = state.pendingSubs.map(s => s.playerIn.id);
        const allInIds = [...confirmedInIds, ...pendingInIds];
        return state.benchPlayers.filter(p => !allInIds.includes(p.id)).sort((a, b) => a.positionSort - b.positionSort);
    }

    function resetSubstitutions() {
        const state = ctx();
        state.selectedPlayerOut = null;
        state.selectedPlayerIn = null;
        state.livePitchSelectedOutId = null;
        state.pendingSubs = [];
    }

    function addPendingSub() {
        const state = ctx();
        if (!state.selectedPlayerOut || !state.selectedPlayerIn) return;
        state.pendingSubs.push({
            playerOut: { ...state.selectedPlayerOut },
            playerIn: { ...state.selectedPlayerIn },
        });
        state.selectedPlayerOut = null;
        state.selectedPlayerIn = null;
        state.livePitchSelectedOutId = null;
    }

    function removePendingSub(index) {
        ctx().pendingSubs.splice(index, 1);
    }

    /**
     * Get the current active lineup players (starting XI with substitutions applied).
     */
    function getActiveLineupPlayers() {
        const state = ctx();
        const subbedOutIds = new Set(state.substitutionsMade.map(s => s.playerOutId));
        const subbedInIds = new Set(state.substitutionsMade.map(s => s.playerInId));

        // Filter starting lineup: remove subbed out players
        const remaining = state.lineupPlayers.filter(p => !subbedOutIds.has(p.id));

        // Add subbed-in players from bench
        const subbedIn = state.benchPlayers.filter(p => subbedInIds.has(p.id));

        return [...remaining, ...subbedIn];
    }

    return {
        get redCardedPlayerIds() { return getRedCardedPlayerIds(); },
        get yellowCardedPlayerIds() { return getYellowCardedPlayerIds(); },
        isPlayerYellowCarded,
        get effectiveMaxSubstitutions() { return getEffectiveMaxSubstitutions(); },
        get effectiveMaxWindows() { return getEffectiveMaxWindows(); },
        get windowsUsed() { return getWindowsUsed(); },
        get hasWindowsLeft() { return getHasWindowsLeft(); },
        get subsRemaining() { return getSubsRemaining(); },
        get canSubstitute() { return getCanSubstitute(); },
        get canAddMoreToPending() { return getCanAddMoreToPending(); },
        get availableLineupForPicker() { return getAvailableLineupForPicker(); },
        get availableBenchForPicker() { return getAvailableBenchForPicker(); },
        // Keep old getter aliases for backward compatibility with templates
        get availableLineupPlayers() { return getAvailableLineupForPicker(); },
        get availableBenchPlayers() { return getAvailableBenchForPicker(); },
        resetSubstitutions,
        addPendingSub,
        removePendingSub,
        getActiveLineupPlayers,
    };
}
