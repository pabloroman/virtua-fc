/**
 * Glue layer around atmosphere-generator.js and match-summary-generator.js.
 *
 * Atmosphere events (shots, fouls, contextual + tactical narratives) are a
 * DERIVED VIEW of the canonical server-truth event list. Callers mutate
 * `c.realEvents` / `c.realExtraTimeEvents` and then call
 * `recomputeRegularAtmosphere()` / `recomputeETAtmosphere()`; this module
 * runs the generators against the fresh real-event list and rebuilds the
 * merged `c.events` / `c.extraTimeEvents` arrays in lockstep.
 *
 * The "regenerate-then-merge" race window that produced the original
 * sent-off-player atmosphere bugs cannot exist here: there is no separate
 * `availabilityEvents` parameter to keep in sync — atmosphere only ever
 * runs after `realEvents` is fully up to date.
 */
import {
    generateRegularTimeAtmosphere,
    generateExtraTimeAtmosphere,
    generateTacticalNarratives,
    addGoalNarratives,
} from './atmosphere-generator.js';
import { generateMatchSummary } from './match-summary-generator.js';

export function createAtmosphereGlue(ctx) {
    function atmosphereConfig() {
        const c = ctx();
        return {
            homeTeamId: c.homeTeamId,
            awayTeamId: c.awayTeamId,
            homeTeamName: c.homeTeamName,
            awayTeamName: c.awayTeamName,
            homePlayers: c.homeLineupRoster,
            awayPlayers: c.awayLineupRoster,
            homeScore: c.finalHomeScore,
            awayScore: c.finalAwayScore,
            venueName: c.venueName,
            isNeutralVenue: c.isNeutralVenue ?? false,
            venueEnPhrase: c.venueEnPhrase,
            venueElPhrase: c.venueElPhrase,
            venueDePhrase: c.venueDePhrase,
            homeArticle: c.homeArticle,
            awayArticle: c.awayArticle,
            narrativeTemplates: c.narrativeTemplates,
            userTeamId: c.userTeamId,
            isKnockout: c.isKnockout,
            isTwoLeggedTie: c.isTwoLeggedTie,
            // Per-match stoppage durations — required so the contextual
            // second-half-start narrative can land just past the end of
            // 1H stoppage (rather than at the hardcoded 45.9 boundary,
            // which sits inside the stoppage window when fhs > 0).
            firstHalfStoppage: c.firstHalfStoppage,
            secondHalfStoppage: c.secondHalfStoppage,
            tactics: {
                userPlayingStyle: c.activePlayingStyle,
                userPressing: c.activePressing,
                userDefLine: c.activeDefLine,
                userMentality: c.activeMentality,
                opponentPlayingStyle: c.opponentPlayingStyle,
                opponentPressing: c.opponentPressing,
                opponentDefLine: c.opponentDefLine,
                opponentMentality: c.opponentMentality,
            },
        };
    }

    function mergeSorted(a, b) {
        return [...a, ...b].sort((x, y) => x.minute - y.minute);
    }

    return {
        _atmosphereConfig() {
            return atmosphereConfig();
        },

        /**
         * Rebuild regular-time atmosphere from `c.realEvents`. Writes
         * `c.atmosphereEvents` and `c.events` (merged sorted view).
         * Idempotent: calling repeatedly with the same realEvents yields
         * the same merged shape (atmosphere RNG is non-deterministic, so
         * the specific atmosphere events differ, but the invariant holds).
         */
        recomputeRegularAtmosphere() {
            const c = ctx();
            const cfg = atmosphereConfig();
            addGoalNarratives(c.realEvents, cfg);
            const atmosphere = generateRegularTimeAtmosphere({ ...cfg, allEvents: c.realEvents });
            const tactical = generateTacticalNarratives({ ...cfg, allEvents: c.realEvents });
            c.atmosphereEvents = [...atmosphere, ...tactical];
            c.events = mergeSorted(c.realEvents, c.atmosphereEvents);
        },

        /**
         * Rebuild extra-time atmosphere from `c.realExtraTimeEvents`,
         * using the regular-time realEvents as additional availability
         * context. Writes `c.atmosphereExtraTimeEvents` and
         * `c.extraTimeEvents`.
         */
        recomputeETAtmosphere() {
            const c = ctx();
            const cfg = atmosphereConfig();
            addGoalNarratives(c.realExtraTimeEvents, cfg);
            const allRealEvents = [...c.realEvents, ...c.realExtraTimeEvents];
            const atmosphere = generateExtraTimeAtmosphere({ ...cfg, allEvents: allRealEvents });
            c.atmosphereExtraTimeEvents = atmosphere;
            c.extraTimeEvents = mergeSorted(c.realExtraTimeEvents, c.atmosphereExtraTimeEvents);
        },

        _generateMatchSummary() {
            const c = ctx();
            return generateMatchSummary({
                ...atmosphereConfig(),
                mvpPlayerName: c.mvpPlayerName,
                mvpPlayerTeamId: c.mvpPlayerTeamId,
                hasExtraTime: c.hasExtraTime,
                etHomeScore: c.etHomeScore,
                etAwayScore: c.etAwayScore,
                penaltyResult: c.penaltyResult,
                allEvents: [...c.events, ...c.extraTimeEvents],
                isKnockout: c.isKnockout,
                isTwoLeggedTie: c.isTwoLeggedTie,
                isSecondLeg: c.twoLeggedInfo !== null,
                knockoutRoundNumber: c.knockoutRoundNumber,
                isFinal: c.isFinal,
                competitionRole: c.competitionRole,
                competitionName: c.competitionName,
                homeForm: c.homeForm,
                awayForm: c.awayForm,
                homePosition: c.homePosition,
                awayPosition: c.awayPosition,
            });
        },
    };
}
