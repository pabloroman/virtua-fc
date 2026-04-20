import {
    calculatePlayerRatings,
    ratingColor as _ratingColor,
    countEvents,
    buildSubstitutionMap,
    performanceToBaseRating,
} from './player-ratings.js';
import { PHASE } from './match-phases.js';

/**
 * Wrappers around `modules/player-ratings.js` that read live-match state
 * through `ctx()`. Previously inlined at the bottom of live-match.js.
 */
export function createRatingsGlue(ctx) {
    function allEvents() {
        const c = ctx();
        return [...c.events, ...(c.extraTimeEvents || [])];
    }

    return {
        recalculatePlayerRatings() {
            const c = ctx();
            const events = allEvents();
            const subMap = buildSubstitutionMap(events);

            // Build sub-in player list for rating calculation: any bench player
            // (user or opponent) who came on and has cached performance data.
            const subsIn = [];
            for (const bp of c.benchPlayers) {
                if (bp.performance != null && subMap.subbedIn[bp.id]) {
                    subsIn.push({
                        id: bp.id,
                        performance: bp.performance,
                        positionGroup: bp.positionGroup,
                        teamId: c.userTeamId,
                    });
                }
            }
            for (const bp of c.opponentBenchPlayers) {
                if (bp.performance != null && subMap.subbedIn[bp.id]) {
                    subsIn.push({
                        id: bp.id,
                        performance: bp.performance,
                        positionGroup: bp.positionGroup,
                        teamId: bp.teamId,
                    });
                }
            }

            c.playerRatings = calculatePlayerRatings(
                c.homeLineupRoster,
                c.awayLineupRoster,
                events,
                c.finalHomeScore,
                c.finalAwayScore,
                c.homeTeamId,
                c.awayTeamId,
                subsIn,
            );
        },

        ratingColor(rating) {
            return _ratingColor(rating);
        },

        /**
         * Live (pre-full-time) rating for a player, derived solely from the
         * cached performance modifier — no event, score, or card bonuses.
         *
         * Only exposed during the half-time (and ET half-time) substitution
         * window. Outside those windows the rating has no actionable value —
         * showing it during live play would just be noise, and showing it at
         * full time would compete with the proper event-weighted rating.
         *
         * Returns null when not in a half-time window or when no performance
         * data is available for the player.
         */
        getBaseRating(playerId) {
            if (!playerId) return null;
            const c = ctx();
            if (c.phase !== PHASE.HALF_TIME && c.phase !== PHASE.EXTRA_TIME_HALF_TIME) {
                return null;
            }
            const player = c.homeLineupRoster.find(p => p.id === playerId)
                || c.awayLineupRoster.find(p => p.id === playerId)
                || (c.benchPlayers || []).find(p => p.id === playerId);
            if (!player) return null;
            return performanceToBaseRating(player.performance);
        },

        getEventIcons() {
            return countEvents(allEvents());
        },

        getSubMap() {
            return buildSubstitutionMap(allEvents());
        },
    };
}
