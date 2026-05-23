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
    // Ratings are computed from server-truth events only — atmosphere
    // events (shots, fouls, narratives) are cosmetic and don't contribute
    // goals, cards, or substitution data. Reading realEvents instead of
    // the merged view also avoids picking up regenerated atmosphere
    // numbers after a tactical resimulation.
    function allEvents() {
        const c = ctx();
        const reg = c.realEvents ?? c.events ?? [];
        const et = c.realExtraTimeEvents ?? c.extraTimeEvents ?? [];
        return [...reg, ...et];
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
         * Exposed from half-time onwards (second half, ET, penalties) so the
         * manager can keep monitoring how players are performing while making
         * tactical decisions. Hidden during the first half (not enough data
         * yet) and at full time (where the proper event-weighted rating
         * supersedes this base value).
         *
         * Returns null before half-time, at full time, or when no performance
         * data is available for the player.
         */
        getBaseRating(playerId) {
            if (!playerId) return null;
            const c = ctx();
            if (c.phase === PHASE.PRE_MATCH
                || c.phase === PHASE.FIRST_HALF
                || c.phase === PHASE.FULL_TIME) {
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
