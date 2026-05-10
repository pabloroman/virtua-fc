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
 *   - events:                 MatchResimulationService::formatMatchEvents() output
 *   - homeScore, awayScore:   final scores including ET
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

        // Shape consumed by createRatingsGlue
        events: config.events || [],
        extraTimeEvents: [],
        finalHomeScore: config.homeScore ?? 0,
        finalAwayScore: config.awayScore ?? 0,
        homeTeamId: config.homeTeamId || '',
        awayTeamId: config.awayTeamId || '',
        // Sub-ins live in opponentBenchPlayers because that branch reads
        // each player's own teamId (the user/opponent split doesn't apply
        // to a third-party post-match view).
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
