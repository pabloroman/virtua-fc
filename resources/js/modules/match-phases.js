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
    ET_MIN: 93, // backend routing threshold for tactical changes
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

/**
 * Determine the minute value to send to the backend for a tactical change.
 * During ET-related phases, the currentMinute may be 90 (set by
 * enterRegularTimeEnd), which the backend would interpret as regular time.
 * Clamp to ET_MIN so the backend routes to resimulateExtraTime and doesn't
 * delete stoppage-time goal events.
 */
export function resolveMinuteForTacticalChange(currentMinute, phase) {
    const minute = Math.floor(currentMinute);
    if (isExtraTimePhase(phase) && minute <= MINUTE.ET_MIN) {
        return Math.max(minute, MINUTE.ET_MIN);
    }
    return minute;
}
