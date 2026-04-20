/**
 * Glue layer around atmosphere-generator.js and match-summary-generator.js.
 * Owns the 30-field config object that previously lived inline in live-match.js
 * and the inject/summary methods that dispatched to the generator modules.
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
            venueEnPhrase: c.venueEnPhrase,
            venueElPhrase: c.venueElPhrase,
            venueDePhrase: c.venueDePhrase,
            homeArticle: c.homeArticle,
            awayArticle: c.awayArticle,
            narrativeTemplates: c.narrativeTemplates,
            userTeamId: c.userTeamId,
            isKnockout: c.isKnockout,
            isTwoLeggedTie: c.isTwoLeggedTie,
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

    return {
        _atmosphereConfig() {
            return atmosphereConfig();
        },

        /**
         * Generate atmosphere events and narratives for regular time,
         * merging them into the events array.
         */
        _injectAtmosphere() {
            const c = ctx();
            const cfg = atmosphereConfig();
            addGoalNarratives(c.events, cfg);
            const atmosphere = generateRegularTimeAtmosphere({ ...cfg, allEvents: c.events });
            const tactical = generateTacticalNarratives({ ...cfg, allEvents: c.events });
            const allAtmosphere = [...atmosphere, ...tactical];
            if (allAtmosphere.length) {
                c.events = [...c.events, ...allAtmosphere].sort((a, b) => a.minute - b.minute);
            }
        },

        /**
         * Generate atmosphere events and narratives for extra time,
         * merging them into the extraTimeEvents array.
         */
        _injectETAtmosphere() {
            const c = ctx();
            const cfg = atmosphereConfig();
            addGoalNarratives(c.extraTimeEvents, cfg);
            const allEvents = [...c.events, ...c.extraTimeEvents];
            const atmosphere = generateExtraTimeAtmosphere({ ...cfg, allEvents });
            if (atmosphere.length) {
                c.extraTimeEvents = [...c.extraTimeEvents, ...atmosphere].sort((a, b) => a.minute - b.minute);
            }
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
                competitionRole: c.competitionRole,
                competitionName: c.competitionName,
                homeForm: c.homeForm,
                awayForm: c.awayForm,
                homePosition: c.homePosition,
                awayPosition: c.awayPosition,
                tournamentResultType: c.tournamentResultType,
            });
        },
    };
}
