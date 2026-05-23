/**
 * Match phase enum + predicates + minute constants. Single source of truth
 * for the ~40 sites across live-match.js that compared `phase === '...'`
 * or used raw minute thresholds (45, 90, 93, 105, 120).
 */

export const PHASE = Object.freeze({
    PRE_MATCH: 'pre_match',
    FIRST_HALF: 'first_half',
    HALF_TIME: 'half_time',
    SECOND_HALF: 'second_half',
    GOING_TO_EXTRA_TIME: 'going_to_extra_time',
    EXTRA_TIME_FIRST_HALF: 'extra_time_first_half',
    EXTRA_TIME_HALF_TIME: 'extra_time_half_time',
    EXTRA_TIME_SECOND_HALF: 'extra_time_second_half',
    PENALTIES: 'penalties',
    FULL_TIME: 'full_time',
});

export const MINUTE = Object.freeze({
    FIRST_HALF_END: 45,
    REGULAR_TIME_END: 90,
    ET_FIRST_HALF_END: 105,
    ET_END: 120,
});

// Minutes at which a substitution doesn't consume a window (free subs).
export const FREE_SUB_WINDOW_MINUTES = [45, 90, 105];

export function isExtraTimePhase(phase) {
    return phase === PHASE.GOING_TO_EXTRA_TIME
        || phase === PHASE.EXTRA_TIME_FIRST_HALF
        || phase === PHASE.EXTRA_TIME_HALF_TIME
        || phase === PHASE.EXTRA_TIME_SECOND_HALF;
}

export function isActiveExtraTimePhase(phase) {
    // True only while the ET clock is advancing (excludes pre-ET transition).
    return phase === PHASE.EXTRA_TIME_FIRST_HALF
        || phase === PHASE.EXTRA_TIME_HALF_TIME
        || phase === PHASE.EXTRA_TIME_SECOND_HALF;
}

export function isPlayingPhase(phase) {
    return phase === PHASE.FIRST_HALF
        || phase === PHASE.SECOND_HALF
        || phase === PHASE.EXTRA_TIME_FIRST_HALF
        || phase === PHASE.EXTRA_TIME_SECOND_HALF;
}

export function isHalfTimeLike(phase) {
    return phase === PHASE.HALF_TIME || phase === PHASE.EXTRA_TIME_HALF_TIME;
}

// Cutoff minute for "everything that has happened so far" when the user
// submits a tactical action. During play, that's just the live clock. At
// half-time the live clock has been snapped back to the half boundary
// (45 / 105), which loses the stoppage-time events the user already
// watched — so we lift the cutoff to the end of the half's stoppage
// window. Used for both the POST payload to the resimulation endpoint
// and the client-side event/feed filters; keeping them in sync prevents
// stoppage goals from being reverted server-side or stripped client-side.
export function effectiveSubmissionMinute(state) {
    if (state.phase === PHASE.HALF_TIME) {
        return MINUTE.FIRST_HALF_END + (state.firstHalfStoppage || 0);
    }
    if (state.phase === PHASE.EXTRA_TIME_HALF_TIME) {
        return MINUTE.ET_FIRST_HALF_END + (state.etFirstHalfStoppage || 0);
    }
    return Math.floor(state.currentMinute);
}

