/**
 * Knockout + tournament result getters. Pure functions of score state,
 * penalty results, and knockout flags — exposed as Alpine getters via a
 * factory that receives a `ctx()` accessor returning the component.
 */

export function createKnockoutOutcome(ctx) {
    function userWonByScore(home, away) {
        const c = ctx();
        if (home === away) return null;
        const homeWon = home > away;
        return c.userTeamId === c.homeTeamId ? homeWon : !homeWon;
    }

    function userWonByPenalties() {
        const c = ctx();
        const { home, away } = c.penaltyResult;
        const homeWon = home > away;
        return c.userTeamId === c.homeTeamId ? homeWon : !homeWon;
    }

    return {
        // Works for all knockout matches (league cups, UEFA, tournaments).
        // Returns 'win', 'loss', or null (non-knockout / league draws).
        get knockoutOutcome() {
            const c = ctx();
            if (!c.isKnockout) return null;
            if (c.penaltyResult) return userWonByPenalties() ? 'win' : 'loss';
            const result = userWonByScore(c.homeScore, c.awayScore);
            return result === null ? null : (result ? 'win' : 'loss');
        },

        get playerWon() {
            const c = ctx();
            if (!c.isTournamentKnockout) return null;
            if (c.penaltyResult) return userWonByPenalties();
            return userWonByScore(c.homeScore, c.awayScore);
        },

        get tournamentResultType() {
            const c = ctx();
            if (!c.isTournamentKnockout || this.playerWon === null) return null;
            const round = c.knockoutRoundNumber;
            const won = this.playerWon;

            // Round 6 = Final
            if (round === 6) return won ? 'champion' : 'runner_up';
            // Round 5 = Third-place match
            if (round === 5) return won ? 'third' : 'fourth';
            // Round 4 = Semi-final
            if (round === 4) return won ? 'to_final' : 'to_third_place';
            // Rounds 1-3 = R32/R16/QF
            return won ? 'advance' : 'eliminated';
        },

        get isTournamentDecisive() {
            const type = this.tournamentResultType;
            if (!type) return false;
            // Decisive = tournament is over for the player (no more matches after this)
            return ['champion', 'runner_up', 'third', 'fourth', 'eliminated'].includes(type);
        },

        get isChampion() {
            return this.tournamentResultType === 'champion';
        },
    };
}
