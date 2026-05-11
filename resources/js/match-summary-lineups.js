/**
 * Read-only Alpine scope used by the match-summary lineups/ratings tab.
 *
 * Mirrors the slice of the `liveMatch` factory's state that the shared
 * `partials/live-match/lineups-roster.blade.php` partial reads, so the same
 * markup renders identically post-match. Ratings are precomputed server-side
 * (MatchRatingCalculator) and persisted in `game_match_player_ratings`, so
 * we read `p.rating` directly off each roster entry. The shared `createRatingsGlue`
 * module is still mixed in for its `ratingColor`, `getEventIcons`, and
 * `getSubMap` helpers consumed by the shared roster partial.
 *
 * Config shape (built by MatchSummaryPresenter, passed via Js::from):
 *   - homeRoster, awayRoster: [{ id, name, positionAbbr, positionGroup, performance?, rating? }]
 *   - subInPlayers:           [{ id, positionGroup, performance?, rating?, teamId }]
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
        // the helpers (event icons, sub map, colour tiers) behave identically.
        events: config.events || [],
        extraTimeEvents: config.extraTimeEvents || [],
        finalHomeScore: config.homeScore ?? 0,
        finalAwayScore: config.awayScore ?? 0,
        homeTeamId: config.homeTeamId || '',
        awayTeamId: config.awayTeamId || '',
        benchPlayers: [],
        opponentBenchPlayers: config.subInPlayers || [],
        userTeamId: null,

        init() {
            _self = this;

            // Server-provided ratings from game_match_player_ratings. Falls back
            // to the JS recalc if a row is missing for a player (defensive — the
            // table is populated for every starter + sub-in at finalization).
            const seeded = {};
            const seedFrom = (list) => {
                for (const p of list) {
                    if (p && p.rating != null) seeded[p.id] = p.rating;
                }
            };
            seedFrom(this.homeLineupRoster);
            seedFrom(this.awayLineupRoster);
            seedFrom(this.opponentBenchPlayers);
            this.playerRatings = seeded;
        },
    };

    mixinModule(state, ratings);
    return state;
}
