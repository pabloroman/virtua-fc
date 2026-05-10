/**
 * Read-only Alpine scope used by the match-summary lineups/ratings tab.
 *
 * Mirrors the slice of the `liveMatch` factory's state that the shared
 * `partials/live-match/lineups-roster.blade.php` partial reads, so the same
 * markup renders identically post-match. Player ratings are computed via the
 * shared `createRatingsGlue` module — the same one live-match uses — so the
 * formula and colour tiers stay in lockstep.
 *
 * Config shape (built by MatchSummaryPresenter, passed via Js::from):
 *   - homeRoster, awayRoster: [{ id, name, positionAbbr, positionGroup, performance? }]
 *   - subInPlayers:           [{ id, positionGroup, performance?, teamId }]
 *   - events:                 formatMatchEvents() output for minute ≤93
 *   - extraTimeEvents:        formatMatchEvents() output for minute >93
 *   - homeScore, awayScore:   90-minute scores (no ET), matching what
 *                             ShowLiveMatch passes as finalHomeScore/Away
 *   - homeTeamId, awayTeamId: team IDs
 */
import { mixinModule } from './modules/_mixin.js';
import { createRatingsGlue } from './modules/ratings-glue.js';

export default function matchSummaryLineups(config) {
    let _self = null;
    const ctx = () => _self;
    const ratings = createRatingsGlue(ctx);

    const state = {
        // Shape consumed by partials/live-match/lineups-roster.blade.php
        homeLineupRoster: config.homeRoster || [],
        awayLineupRoster: config.awayRoster || [],
        phase: 'full_time',
        playerRatings: {},

        // Shape consumed by createRatingsGlue — mirrors live-match exactly so
        // the same fixture yields the same ratings in both views.
        events: config.events || [],
        extraTimeEvents: config.extraTimeEvents || [],
        finalHomeScore: config.homeScore ?? 0,
        finalAwayScore: config.awayScore ?? 0,
        homeTeamId: config.homeTeamId || '',
        awayTeamId: config.awayTeamId || '',
        // No user/opponent split in a third-party post-match view: every
        // sub-in carries its own teamId, so route them through the opponent
        // bench branch (which reads each player's teamId) and leave
        // userTeamId null to skip the user-side branch entirely.
        benchPlayers: [],
        opponentBenchPlayers: config.subInPlayers || [],
        userTeamId: null,

        init() {
            _self = this;
            this.recalculatePlayerRatings();
        },
    };

    mixinModule(state, ratings);
    return state;
}
