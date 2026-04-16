/**
 * Synthetic match-stats module.
 *
 * The match simulator doesn't track passes, corners, offsides, or fouls — but
 * managers expect to see them on the live stats panel. This module produces
 * plausible numbers that scale with elapsed minute, possession share, and
 * tactical inputs (playing style, mentality, pressing, defensive line). Stats
 * are deterministic per match (seeded on matchId) so they don't jitter across
 * re-renders.
 *
 * Design rules:
 * - Possession is the dominant signal. It already reflects team strength
 *   server-side, so a dominant possession team produces dominant counters.
 * - Tactical modifiers are second-order. Jitter is ±3% at most — never large
 *   enough to invert the possession ordering between two close teams.
 * - Each stat has an explicit target band (e.g. corners [2, 10] avg 5, fouls
 *   [5, 20] avg 12). Final outputs are clamped to that band.
 *
 * The module exposes:
 * - `createMatchStats(ctx)` — Alpine factory returning the four `getSynthetic*`
 *   methods. Mixed into the live-match component via `mixinModule`.
 * - `compute*` pure functions — same math, decoupled from Alpine, so the
 *   formulas can be tuned and read without wading through a 2000-line file.
 */

/**
 * Stable per-match, per-stat, per-side multiplier in [0.97, 1.03]. Keeps two
 * sides with identical tactics/possession from looking byte-identical without
 * overwhelming the underlying signal.
 */
export function syntheticStatSeed(matchId, stat, side) {
    const key = `${matchId}:${stat}:${side}`;
    let hash = 0;
    for (let i = 0; i < key.length; i++) {
        hash = ((hash << 5) - hash + key.charCodeAt(i)) | 0;
    }
    return 0.97 + ((Math.abs(hash) % 61) / 1000);
}

/**
 * Elapsed minutes used for stat accrual. Caps at 93 in regular time and 123
 * during extra time / penalties so numbers stop climbing after the whistle.
 */
export function elapsedMinutesForStats(currentMinute, phase) {
    const inExtraTime = phase === 'extra_time_first_half'
        || phase === 'extra_time_second_half'
        || phase === 'extra_time_half_time'
        || phase === 'penalties';
    const cap = inExtraTime ? 123 : 93;
    return Math.max(0, Math.min(currentMinute ?? 0, cap));
}

/**
 * Map a home/away side onto the user-centric tactics config. The user may be
 * either home or away, so we need `userTeamId` + `homeTeamId` to decide which
 * slot on the tactics object belongs to this side.
 *
 * Returns `{ style, mentality, pressing, defLine }`.
 */
export function resolveTacticsForSide({ side, userTeamId, homeTeamId, tactics }) {
    const t = tactics || {};
    const userIsHome = userTeamId === homeTeamId;
    const isUserSide = (side === 'home') === userIsHome;
    return isUserSide
        ? {
            style: t.userPlayingStyle ?? t.activePlayingStyle ?? 'balanced',
            mentality: t.userMentality ?? t.activeMentality ?? 'balanced',
            pressing: t.userPressing ?? t.activePressing ?? 'standard',
            defLine: t.userDefLine ?? t.activeDefLine ?? 'normal',
        }
        : {
            style: t.opponentPlayingStyle ?? 'balanced',
            mentality: t.opponentMentality ?? 'balanced',
            pressing: t.opponentPressing ?? 'standard',
            defLine: t.opponentDefLine ?? 'normal',
        };
}

/**
 * Passes per minute of OWN possession for a given playing style. Possession-
 * style sides link many short passes; direct sides play fewer, longer balls.
 */
function passRateForStyle(style) {
    switch (style) {
        case 'possession': return 18;
        case 'counter_attack': return 10;
        case 'direct': return 8;
        case 'balanced':
        default: return 13;
    }
}

/**
 * Pass count ≈ pass-rate-while-possessing × share of possession × elapsed.
 * A POSSESSION side at 70% possession ends ~1100 passes; a DIRECT side at 30%
 * ends ~200. The formula is linear in possession share, so the dominant team
 * leads by at least the possession ratio.
 */
export function computePasses({ possessionShare, style, elapsedMinutes, seed }) {
    const share = clamp01(possessionShare ?? 0.5);
    const rate = passRateForStyle(style);
    const value = rate * share * (elapsedMinutes ?? 0) * (seed ?? 1);
    return Math.floor(Math.max(0, value));
}

/**
 * Corner count in [2, 10] with midpoint 5. A sigmoid on possession (steepness
 * k=4, centered at 50%) maps possession share smoothly into the band, so
 * extreme splits saturate at 10 / 2 instead of overshooting. Attacking
 * mentality and possession-style add modest boosts; the combined tactical
 * multiplier is capped at ±30% so it can't bust the band on its own.
 */
export function computeCorners({ possessionShare, style, mentality, elapsedMinutes, seed }) {
    const share = clamp01(possessionShare ?? 0.5);
    const s = 1 / (1 + Math.exp(-4 * (share - 0.5)));
    const base = 2 + s * (10 - 2);

    const mentalityMod = mentality === 'attacking' ? 1.20
        : mentality === 'defensive' ? 0.80 : 1.00;
    const styleMod = style === 'possession' ? 1.10
        : style === 'counter_attack' ? 0.90 : 1.00;
    const tacticalScale = clamp(mentalityMod * styleMod, 0.70, 1.30);

    const timeFraction = (elapsedMinutes ?? 0) / 90;
    const value = base * tacticalScale * timeFraction * (seed ?? 1);
    return Math.max(0, clampInt(Math.floor(value), Math.ceil(2 * timeFraction), Math.floor(10 * timeFraction)));
}

/**
 * Offside count. Driven by THIS side's attacking risk-taking (style,
 * mentality) and the OPPONENT's defensive line height — a high line multiplies
 * offsides, a deep block kills them. Independent of possession share because
 * offsides track runs behind the line, not sustained ball control.
 */
export function computeOffsides({ ownStyle, ownMentality, oppDefLine, elapsedMinutes, seed }) {
    const styleMod = ownStyle === 'direct' ? 1.35
        : ownStyle === 'counter_attack' ? 1.20
        : ownStyle === 'possession' ? 0.75
        : 1.00;
    const mentalityMod = ownMentality === 'attacking' ? 1.25
        : ownMentality === 'defensive' ? 0.85 : 1.00;
    const oppLineMod = oppDefLine === 'high_line' ? 1.40
        : oppDefLine === 'deep' ? 0.65 : 1.00;

    const timeFraction = (elapsedMinutes ?? 0) / 90;
    const value = 2.5 * timeFraction * styleMod * mentalityMod * oppLineMod * (seed ?? 1);
    return Math.floor(Math.max(0, value));
}

/**
 * Foul count in [5, 20] per 90 min, avg 12. Aggressive pressing and defensive
 * mentality push the count up; the dominated (lower-possession) side also
 * fouls more while chasing the ball. Clamped proportionally to match
 * fraction so partial-period accrual respects the band.
 */
export function computeFouls({ possessionShare, pressing, mentality, elapsedMinutes, seed }) {
    const share = clamp01(possessionShare ?? 0.5);

    const pressingMod = pressing === 'aggressive' ? 1.30
        : pressing === 'low' ? 0.75 : 1.00;
    const mentalityMod = mentality === 'defensive' ? 1.20
        : mentality === 'attacking' ? 0.85 : 1.00;
    const dominatedMod = 1 + 0.4 * (0.5 - share);

    const timeFraction = (elapsedMinutes ?? 0) / 90;
    const base = 12 * pressingMod * mentalityMod * dominatedMod * timeFraction;
    const value = base * (seed ?? 1);
    return Math.max(0, clampInt(Math.floor(value), Math.ceil(5 * timeFraction), Math.floor(20 * timeFraction)));
}

/**
 * Alpine factory. `ctx` returns the live component so getters reflect current
 * state each call. Methods here are thin wrappers that gather state and
 * delegate to the pure `compute*` functions above.
 */
export function createMatchStats(ctx) {
    function tacticsForSide(state, side) {
        return resolveTacticsForSide({
            side,
            userTeamId: state.userTeamId,
            homeTeamId: state.homeTeamId,
            tactics: {
                userPlayingStyle: state.activePlayingStyle,
                userMentality: state.activeMentality,
                userPressing: state.activePressing,
                userDefLine: state.activeDefLine,
                opponentPlayingStyle: state.opponentPlayingStyle,
                opponentMentality: state.opponentMentality,
                opponentPressing: state.opponentPressing,
                opponentDefLine: state.opponentDefLine,
            },
        });
    }

    function possessionShareForSide(state, side) {
        const home = (state._basePossession ?? 50) / 100;
        return side === 'home' ? home : (1 - home);
    }

    function elapsed(state) {
        return elapsedMinutesForStats(state.currentMinute, state.phase);
    }

    return {
        getSyntheticPasses(side) {
            const state = ctx();
            const { style } = tacticsForSide(state, side);
            return computePasses({
                possessionShare: possessionShareForSide(state, side),
                style,
                elapsedMinutes: elapsed(state),
                seed: syntheticStatSeed(state.matchId, 'passes', side),
            });
        },

        getSyntheticCorners(side) {
            const state = ctx();
            const { style, mentality } = tacticsForSide(state, side);
            return computeCorners({
                possessionShare: possessionShareForSide(state, side),
                style,
                mentality,
                elapsedMinutes: elapsed(state),
                seed: syntheticStatSeed(state.matchId, 'corners', side),
            });
        },

        getSyntheticOffsides(side) {
            const state = ctx();
            const own = tacticsForSide(state, side);
            const opp = tacticsForSide(state, side === 'home' ? 'away' : 'home');
            return computeOffsides({
                ownStyle: own.style,
                ownMentality: own.mentality,
                oppDefLine: opp.defLine,
                elapsedMinutes: elapsed(state),
                seed: syntheticStatSeed(state.matchId, 'offsides', side),
            });
        },

        getSyntheticFouls(side) {
            const state = ctx();
            const { pressing, mentality } = tacticsForSide(state, side);
            return computeFouls({
                possessionShare: possessionShareForSide(state, side),
                pressing,
                mentality,
                elapsedMinutes: elapsed(state),
                seed: syntheticStatSeed(state.matchId, 'fouls', side),
            });
        },
    };
}

// ---- helpers ----

function clamp(n, lo, hi) {
    return Math.min(hi, Math.max(lo, n));
}

function clamp01(n) {
    return clamp(n, 0, 1);
}

function clampInt(n, lo, hi) {
    // When elapsedMinutes is 0 the proportional band collapses to [0, 0];
    // fall back to lo=0 so we don't invert the range.
    if (hi < lo) return 0;
    return clamp(n, lo, hi);
}
