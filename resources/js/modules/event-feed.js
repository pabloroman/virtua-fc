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
        get firstHalfEvents() {
            return groupSubstitutions(ctx().revealedEvents.filter(e => e.minute <= MINUTE.FIRST_HALF_END));
        },

        get secondHalfEvents() {
            return groupSubstitutions(ctx().revealedEvents.filter(
                e => e.minute > MINUTE.FIRST_HALF_END && e.minute <= MINUTE.REGULAR_TIME_END,
            ));
        },

        get etFirstHalfEvents() {
            return groupSubstitutions(ctx().revealedEvents.filter(
                e => e.minute > MINUTE.REGULAR_TIME_END && e.minute <= MINUTE.ET_FIRST_HALF_END,
            ));
        },

        get etSecondHalfEvents() {
            return groupSubstitutions(ctx().revealedEvents.filter(e => e.minute > MINUTE.ET_FIRST_HALF_END));
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
        get displayMinute() {
            const c = ctx();
            const m = Math.floor(c.currentMinute);
            switch (c.phase) {
                case PHASE.PRE_MATCH: return '0';
                case PHASE.HALF_TIME: return String(MINUTE.FIRST_HALF_END);
                case PHASE.GOING_TO_EXTRA_TIME: return String(MINUTE.REGULAR_TIME_END);
                case PHASE.EXTRA_TIME_HALF_TIME: return String(MINUTE.ET_FIRST_HALF_END);
                case PHASE.PENALTIES: return String(MINUTE.ET_END);
                case PHASE.FULL_TIME:
                    return c.hasExtraTime ? String(MINUTE.ET_END) : String(MINUTE.REGULAR_TIME_END);
                case PHASE.EXTRA_TIME_FIRST_HALF:
                case PHASE.EXTRA_TIME_SECOND_HALF:
                    return String(Math.min(m, MINUTE.ET_END));
                default:
                    return String(Math.min(m, MINUTE.REGULAR_TIME_END));
            }
        },

        get timelineProgress() {
            const c = ctx();
            return Math.min((c.currentMinute / c.totalMinutes) * 100, 100);
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
