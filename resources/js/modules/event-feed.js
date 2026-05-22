/**
 * Event feed display, filtering, grouping, and timeline marker helpers.
 * All getters/methods are read-only views over the component's event
 * arrays; no mutation happens here.
 */
import { PHASE, MINUTE, isExtraTimePhase } from './match-phases.js';

const EVENT_ICONS = Object.freeze({
    goal: '\u26BD',
    own_goal: '\u26BD',
    yellow_card: '\uD83D\uDFE8',
    red_card: '\uD83D\uDFE5',
    injury: '\uD83C\uDFE5',
    substitution: '\uD83D\uDD04',
});

// Phase strings emitted by the server come from the MatchPhase enum.
// We treat regulation/ET stoppage as belonging to the same half as the
// preceding open play, so 45+N' events sit before the half-time line.
const FIRST_HALF_PHASES     = new Set(['first_half', 'first_half_stoppage']);
const SECOND_HALF_PHASES    = new Set(['second_half', 'second_half_stoppage']);
const ET_FIRST_HALF_PHASES  = new Set(['et_first_half', 'et_first_half_stoppage']);
const ET_SECOND_HALF_PHASES = new Set(['et_second_half', 'et_second_half_stoppage', 'penalties']);

function eventHalf(event) {
    if (event.phase) {
        if (FIRST_HALF_PHASES.has(event.phase))     return 'first';
        if (SECOND_HALF_PHASES.has(event.phase))    return 'second';
        if (ET_FIRST_HALF_PHASES.has(event.phase))  return 'etFirst';
        if (ET_SECOND_HALF_PHASES.has(event.phase)) return 'etSecond';
    }
    // Fallback for client-injected events with no phase. These all land
    // on or near a half boundary (45, 45.9, 90, 105) so the simple
    // threshold check is sufficient.
    if (event.minute <= MINUTE.FIRST_HALF_END)    return 'first';
    if (event.minute <= MINUTE.REGULAR_TIME_END)  return 'second';
    if (event.minute <= MINUTE.ET_FIRST_HALF_END) return 'etFirst';
    return 'etSecond';
}

export function createEventFeed(ctx) {
    function groupSubstitutions(events) {
        const result = [];
        for (const event of events) {
            if (event.type === 'substitution') {
                const prev = result[result.length - 1];
                if (prev && prev.type === 'substitution_group' && prev.minute === event.minute && prev.teamId === event.teamId) {
                    prev.substitutions.push({ playerInName: event.playerInName, playerName: event.playerName });
                    continue;
                }
                if (prev && prev.type === 'substitution' && prev.minute === event.minute && prev.teamId === event.teamId) {
                    result[result.length - 1] = {
                        type: 'substitution_group',
                        minute: prev.minute,
                        teamId: prev.teamId,
                        substitutions: [
                            { playerInName: prev.playerInName, playerName: prev.playerName },
                            { playerInName: event.playerInName, playerName: event.playerName },
                        ],
                    };
                    continue;
                }
            }
            result.push(event);
        }
        return result;
    }

    return {
        // --- Event grouping by half --------------------------------------
        // Server-emitted events carry their own `phase` (from the
        // MatchPhase enum), which is the canonical answer for which half
        // an event belongs to. Raw `minute` alone is ambiguous in
        // stoppage time: a FIRST_HALF_STOPPAGE 45+2' event has absolute
        // minute 47, so a `minute > 45` filter would misclassify it as
        // second half and render it above the half-time separator.
        // Client-injected events (atmosphere checkpoints, the stoppage
        // announcement, in-game substitutions) don't carry a phase, so
        // we fall back to a minute-threshold check for them.
        get firstHalfEvents() {
            return groupSubstitutions(ctx().revealedEvents.filter(e => eventHalf(e) === 'first'));
        },

        get secondHalfEvents() {
            return groupSubstitutions(ctx().revealedEvents.filter(e => eventHalf(e) === 'second'));
        },

        get etFirstHalfEvents() {
            return groupSubstitutions(ctx().revealedEvents.filter(e => eventHalf(e) === 'etFirst'));
        },

        get etSecondHalfEvents() {
            return groupSubstitutions(ctx().revealedEvents.filter(e => eventHalf(e) === 'etSecond'));
        },

        // --- Separators --------------------------------------------------
        get showHalfTimeSeparator() {
            const p = ctx().phase;
            return p === PHASE.HALF_TIME || p === PHASE.SECOND_HALF || p === PHASE.FULL_TIME
                || isExtraTimePhase(p) || p === PHASE.PENALTIES;
        },

        get showETHalfTimeSeparator() {
            const c = ctx();
            return c.phase === PHASE.EXTRA_TIME_HALF_TIME
                || c.phase === PHASE.EXTRA_TIME_SECOND_HALF
                || ((c.phase === PHASE.PENALTIES || c.phase === PHASE.FULL_TIME) && c.hasExtraTime);
        },

        get showExtraTimeSeparator() {
            const c = ctx();
            return c.isInExtraTime || c.phase === PHASE.PENALTIES
                || (c.phase === PHASE.FULL_TIME && c.hasExtraTime);
        },

        // --- Timeline ----------------------------------------------------
        // Renders the clock minute, using "45+N'" notation when the live
        // clock has crossed into stoppage of any half. Reads the per-match
        // stoppage values from the component state (derived server-side
        // by StoppageCalculator from the actual event mix).
        get displayMinute() {
            const c = ctx();
            const m = Math.floor(c.currentMinute);
            const fhs = c.firstHalfStoppage || 0;
            const shs = c.secondHalfStoppage || 0;
            const etfhs = c.etFirstHalfStoppage || 0;
            const etshs = c.etSecondHalfStoppage || 0;

            const stoppage = (base, extra) => extra > 0 ? `${base}+${extra}` : String(base);

            switch (c.phase) {
                case PHASE.PRE_MATCH: return '0';
                case PHASE.HALF_TIME: return stoppage(MINUTE.FIRST_HALF_END, fhs);
                case PHASE.GOING_TO_EXTRA_TIME: return stoppage(MINUTE.REGULAR_TIME_END, shs);
                case PHASE.EXTRA_TIME_HALF_TIME: return stoppage(MINUTE.ET_FIRST_HALF_END, etfhs);
                case PHASE.PENALTIES: return stoppage(MINUTE.ET_END, etshs);
                case PHASE.FULL_TIME:
                    return c.hasExtraTime
                        ? stoppage(MINUTE.ET_END, etshs)
                        : stoppage(MINUTE.REGULAR_TIME_END, shs);
                case PHASE.FIRST_HALF:
                    return m > MINUTE.FIRST_HALF_END
                        ? stoppage(MINUTE.FIRST_HALF_END, m - MINUTE.FIRST_HALF_END)
                        : String(m);
                case PHASE.SECOND_HALF:
                    return m > MINUTE.REGULAR_TIME_END
                        ? stoppage(MINUTE.REGULAR_TIME_END, m - MINUTE.REGULAR_TIME_END)
                        : String(m);
                case PHASE.EXTRA_TIME_FIRST_HALF:
                    return m > MINUTE.ET_FIRST_HALF_END
                        ? stoppage(MINUTE.ET_FIRST_HALF_END, m - MINUTE.ET_FIRST_HALF_END)
                        : String(m);
                case PHASE.EXTRA_TIME_SECOND_HALF:
                    return m > MINUTE.ET_END
                        ? stoppage(MINUTE.ET_END, m - MINUTE.ET_END)
                        : String(m);
                default:
                    return String(m);
            }
        },

        get timelineProgress() {
            const c = ctx();
            // Pin the bar at the half boundary while the clock ticks
            // through stoppage. `currentMinute` keeps advancing past 45/90
            // so events still reveal on time, but if the bar advances with
            // it the phase-transition snap-back (e.g. enterHalfTime → 45)
            // would visibly move the bar backwards. Clamping the display
            // value here keeps the bar paused at the boundary during
            // stoppage, jumps cleanly through half-time, and resumes
            // advancing from the boundary in the next half.
            let displayMinute = c.currentMinute;
            switch (c.phase) {
                case PHASE.FIRST_HALF:
                    displayMinute = Math.min(displayMinute, MINUTE.FIRST_HALF_END);
                    break;
                case PHASE.SECOND_HALF:
                    displayMinute = Math.min(displayMinute, MINUTE.REGULAR_TIME_END);
                    break;
                case PHASE.EXTRA_TIME_FIRST_HALF:
                    displayMinute = Math.min(displayMinute, MINUTE.ET_FIRST_HALF_END);
                    break;
                case PHASE.EXTRA_TIME_SECOND_HALF:
                    displayMinute = Math.min(displayMinute, MINUTE.ET_END);
                    break;
            }
            return Math.min((displayMinute / c.totalMinutes) * 100, 100);
        },

        get timelineHalfMarker() {
            return ctx().totalMinutes === MINUTE.ET_END
                ? (MINUTE.FIRST_HALF_END / MINUTE.ET_END) * 100
                : 50;
        },

        get timelineETMarker() {
            return (MINUTE.REGULAR_TIME_END / MINUTE.ET_END) * 100;
        },

        get timelineETHalfMarker() {
            return (MINUTE.ET_FIRST_HALF_END / MINUTE.ET_END) * 100;
        },

        getTimelineMarkers() {
            const c = ctx();
            const total = c.totalMinutes;
            return c.revealedEvents
                .filter(e => e.type !== 'assist')
                .map((e, index) => ({
                    position: Math.min((e.minute / total) * 100, 100),
                    type: e.type,
                    minute: e.minute,
                    index,
                }));
        },

        // --- Classification helpers --------------------------------------
        getEventIcon(type) {
            return EVENT_ICONS[type] ?? '\u2022';
        },

        getEventSide(event) {
            const c = ctx();
            if (event.type === 'own_goal') {
                return event.teamId === c.homeTeamId ? 'away' : 'home';
            }
            return event.teamId === c.homeTeamId ? 'home' : 'away';
        },

        isGoalEvent(event) {
            return event.type === 'goal' || event.type === 'own_goal';
        },

        // Atmosphere events are tagged at creation by the atmosphere generator.
        // Using the flag (not a hardcoded type list) keeps this in sync
        // automatically when new atmosphere event types are added.
        isAtmosphereEvent(event) {
            return !!event.atmosphere;
        },

        // --- Stats -------------------------------------------------------
        getStatCount(type, side) {
            const c = ctx();
            const allEvents = [
                ...c.revealedEvents,
                ...c.extraTimeEvents.filter(() => c.revealedEvents.length >= c.events.length),
            ];
            return allEvents.filter(event => {
                if (event.type !== type) return false;
                return this.getEventSide(event) === side;
            }).length;
        },

        // --- Misc -------------------------------------------------------
        groupSubstitutions,
    };
}
