/**
 * Client-side atmosphere event generator.
 *
 * Generates cosmetic shot events (on/off target) and narrative text for
 * substitutions/injuries. These events are purely decorative and don't affect
 * match outcomes — they exist to make the live match feed feel more like real
 * football commentary. Numeric-only counters (passes, corners, offsides,
 * fouls) live in `match-stats.js` and are rendered directly in the stats
 * panel without producing events.
 *
 * @module atmosphere-generator
 */

import { MINUTE } from './match-phases.js';

// Position-group weights for shot attribution (forwards shoot more)
const SHOT_WEIGHTS = {
    Forward: 25,
    Midfielder: 10,
    Defender: 5,
    Goalkeeper: 0,
};

// Tuning constants (cosmetic only — no gameplay impact)
const SHOTS_PER_XG = 3.0;
const ON_TARGET_RATIO = 0.3;
const XG_BASELINE = 1.2; // added to score as xG proxy

/**
 * Format an atmosphere event's clock-time minute the same way the live
 * clock would render it at the moment of reveal: stoppage windows of any
 * half are shown as "45+N'" / "90+N'" / "105+N'" / "120+N'", regular
 * play as a bare integer minute. Server events get this for free via
 * MatchEvent::displayMinute() because they carry (phase, base, stoppage)
 * — atmosphere events only have a sort-minute, so we compute it here
 * using the persisted per-match stoppage durations.
 */
export function formatAtmosphereDisplayMinute(sortMinute, stoppage = {}) {
    const m = Math.floor(sortMinute);
    const fhs   = stoppage.firstHalfStoppage   ?? 0;
    const shs   = stoppage.secondHalfStoppage  ?? 0;
    const etfhs = stoppage.etFirstHalfStoppage ?? 0;
    const etshs = stoppage.etSecondHalfStoppage ?? 0;

    if (m <= MINUTE.FIRST_HALF_END)               return `${m}'`;
    if (m <= MINUTE.FIRST_HALF_END + fhs)         return `${MINUTE.FIRST_HALF_END}+${m - MINUTE.FIRST_HALF_END}'`;
    if (m <= MINUTE.REGULAR_TIME_END)             return `${m}'`;
    if (m <= MINUTE.REGULAR_TIME_END + shs)       return `${MINUTE.REGULAR_TIME_END}+${m - MINUTE.REGULAR_TIME_END}'`;
    if (m <= MINUTE.ET_FIRST_HALF_END)            return `${m}'`;
    if (m <= MINUTE.ET_FIRST_HALF_END + etfhs)    return `${MINUTE.ET_FIRST_HALF_END}+${m - MINUTE.ET_FIRST_HALF_END}'`;
    if (m <= MINUTE.ET_END)                       return `${m}'`;
    return `${MINUTE.ET_END}+${m - MINUTE.ET_END}'`;
}

/**
 * Build a set of player IDs that are OFF the pitch at a given minute,
 * by scanning events for red cards and substitutions.
 *
 * @param {Array} events - All known events (regular + ET)
 * @param {number} atMinute - The minute to check availability for
 * @returns {Set<string>} Set of gamePlayerIds no longer on the pitch
 */
function buildRemovedPlayers(events, atMinute) {
    const removed = new Set();
    for (const e of events) {
        if (e.minute > atMinute) continue;
        if (e.type === 'red_card' || e.type === 'injury') {
            removed.add(e.gamePlayerId);
        }
        if (e.type === 'substitution') {
            removed.add(e.gamePlayerId); // player out
        }
    }
    return removed;
}

/**
 * Build a list of substitute players who have entered the pitch,
 * by scanning substitution events.
 *
 * @param {Array} events - All known events
 * @param {number} atMinute - The minute to check
 * @param {string} teamId - Filter to this team
 * @returns {Array<{name: string, positionGroup: string, id: string}>}
 */
function buildSubstitutesOn(events, atMinute, teamId) {
    const subs = [];
    for (const e of events) {
        if (e.minute > atMinute) continue;
        if (e.type === 'substitution' && e.teamId === teamId && e.playerInName) {
            subs.push({
                name: e.playerInName,
                positionGroup: 'Midfielder', // default — we don't know sub's position
                id: e.metadata?.player_in_id || null,
            });
        }
    }
    return subs;
}

/**
 * Get available players for a team at a given minute.
 *
 * @param {Array} roster - Base roster [{name, positionGroup, id?}]
 * @param {Array} allEvents - All events to scan for removals
 * @param {number} minute - Minute to check
 * @param {string} teamId - Team ID
 * @returns {Array}
 */
function getAvailablePlayers(roster, allEvents, minute, teamId) {
    const removed = buildRemovedPlayers(allEvents, minute);
    const subsOn = buildSubstitutesOn(allEvents, minute, teamId);

    const available = roster.filter(p => !p.id || !removed.has(p.id));
    // Add substitutes who came on
    for (const sub of subsOn) {
        if (!removed.has(sub.id)) {
            available.push(sub);
        }
    }
    return available;
}

/**
 * Pick a random player from a roster using position-group weights.
 *
 * @param {Array} players - [{name, positionGroup}]
 * @param {Object} weights - {positionGroup: weight}
 * @returns {Object|null} The chosen player, or null if empty
 */
function pickWeightedPlayer(players, weights) {
    if (!players.length) return null;

    let totalWeight = 0;
    const weighted = [];
    for (const p of players) {
        const w = weights[p.positionGroup] ?? 1;
        totalWeight += w;
        weighted.push({ player: p, cumulative: totalWeight });
    }

    if (totalWeight === 0) return players[Math.floor(Math.random() * players.length)];

    const roll = Math.random() * totalWeight;
    for (const entry of weighted) {
        if (roll <= entry.cumulative) return entry.player;
    }
    return weighted[weighted.length - 1].player;
}

/**
 * Pick a random narrative template and fill in placeholders.
 *
 * @param {Array<string>} templates - Array of template strings
 * @param {Object} replacements - {':placeholder': 'value'}
 * @returns {string}
 */
function pickNarrative(templates, replacements, { excludeVenue = false } = {}) {
    if (!templates || !templates.length) return '';
    let pool = templates;
    if (excludeVenue) {
        pool = templates.filter(t => !t.includes(':venue'));
        if (!pool.length) return '';
    }
    let text = pool[Math.floor(Math.random() * pool.length)];
    for (const [placeholder, value] of Object.entries(replacements)) {
        text = text.replaceAll(placeholder, value);
    }
    return text.charAt(0).toUpperCase() + text.slice(1);
}

/**
 * Generate a unique random minute within a range, avoiding existing minutes.
 *
 * @param {Set<number>} usedMinutes - Minutes already taken
 * @param {number} min - Minimum minute (inclusive)
 * @param {number} max - Maximum minute (inclusive)
 * @returns {number}
 */
function uniqueMinute(usedMinutes, min, max) {
    const range = max - min + 1;
    // Try random first (fast path)
    for (let attempt = 0; attempt < 20; attempt++) {
        const m = min + Math.floor(Math.random() * range);
        if (!usedMinutes.has(m)) {
            usedMinutes.add(m);
            return m;
        }
    }
    // Fallback: scan for any available minute
    for (let m = min; m <= max; m++) {
        if (!usedMinutes.has(m)) {
            usedMinutes.add(m);
            return m;
        }
    }
    // All minutes taken — just pick one (will overlap but won't crash)
    const m = min + Math.floor(Math.random() * range);
    return m;
}

/**
 * Generate atmosphere events for a single period (half).
 *
 * @param {Object} config
 * @param {string} config.homeTeamId
 * @param {string} config.awayTeamId
 * @param {string} config.homeTeamName
 * @param {string} config.awayTeamName
 * @param {Array} config.homePlayers - [{name, positionGroup, id?}]
 * @param {Array} config.awayPlayers - [{name, positionGroup, id?}]
 * @param {number} config.homeScore - Final home score (xG proxy)
 * @param {number} config.awayScore - Final away score (xG proxy)
 * @param {Object} config.narrativeTemplates - {shotOnTarget:[], shotOffTarget:[], foul:[]}
 * @param {Array} config.allEvents - All events (for player availability checks)
 * @param {number} config.minMinute
 * @param {number} config.maxMinute
 * @returns {Array} Generated atmosphere events
 */
export function generateAtmosphereForPeriod(config) {
    const {
        homeTeamId, awayTeamId, homeTeamName, awayTeamName,
        homeArticle, awayArticle,
        homePlayers, awayPlayers,
        homeScore, awayScore,
        narrativeTemplates, allEvents,
        minMinute, maxMinute,
    } = config;
    const displayMinuteFor = (m) => formatAtmosphereDisplayMinute(m, config);

    const totalMinutes = maxMinute - minMinute + 1;
    const matchFraction = totalMinutes / 90;
    const events = [];

    // Collect minutes already used by real events in this range
    const usedMinutes = new Set();
    for (const e of allEvents) {
        if (e.minute >= minMinute && e.minute <= maxMinute) {
            usedMinutes.add(e.minute);
        }
    }

    const homeF = buildTeamForms(homeTeamName, homeArticle);
    const awayF = buildTeamForms(awayTeamName, awayArticle);

    const teams = [
        {
            id: homeTeamId, name: homeTeamName, forms: homeF,
            opponentName: awayTeamName, opponentForms: awayF,
            players: homePlayers, score: homeScore,
        },
        {
            id: awayTeamId, name: awayTeamName, forms: awayF,
            opponentName: homeTeamName, opponentForms: homeF,
            players: awayPlayers, score: awayScore,
        },
    ];

    // --- Shots ---
    for (const team of teams) {
        const xgProxy = (team.score + XG_BASELINE) * matchFraction;
        const totalShots = Math.round(xgProxy * SHOTS_PER_XG);
        const onTarget = Math.round(totalShots * ON_TARGET_RATIO);
        const offTarget = totalShots - onTarget;

        for (let i = 0; i < onTarget; i++) {
            const minute = uniqueMinute(usedMinutes, minMinute, maxMinute);
            const available = getAvailablePlayers(team.players, allEvents, minute, team.id)
                .filter(p => p.positionGroup !== 'Goalkeeper');
            const player = pickWeightedPlayer(available, SHOT_WEIGHTS);
            if (!player) continue;

            events.push({
                minute,
                displayMinute: displayMinuteFor(minute),
                type: 'shot_on_target',
                atmosphere: true,
                playerName: player.name,
                teamId: team.id,
                gamePlayerId: player.id || null,
                metadata: {
                    narrative: pickNarrative(narrativeTemplates.shotOnTarget || [], {
                        ':del_opponent': team.opponentForms.del,
                        ':el_team': team.forms.el,
                        ':player': player.name,
                        ':team': team.name,
                        ':opponent': team.opponentName,
                    }),
                },
            });
        }

        for (let i = 0; i < offTarget; i++) {
            const minute = uniqueMinute(usedMinutes, minMinute, maxMinute);
            const available = getAvailablePlayers(team.players, allEvents, minute, team.id)
                .filter(p => p.positionGroup !== 'Goalkeeper');
            const player = pickWeightedPlayer(available, SHOT_WEIGHTS);
            if (!player) continue;

            events.push({
                minute,
                displayMinute: displayMinuteFor(minute),
                type: 'shot_off_target',
                atmosphere: true,
                playerName: player.name,
                teamId: team.id,
                gamePlayerId: player.id || null,
                metadata: {
                    narrative: pickNarrative(narrativeTemplates.shotOffTarget || [], {
                        ':del_opponent': team.opponentForms.del,
                        ':el_team': team.forms.el,
                        ':player': player.name,
                        ':team': team.name,
                        ':opponent': team.opponentName,
                    }),
                },
            });
        }
    }

    return events;
}

/**
 * Compute the score at a given minute by scanning goal/own_goal events.
 */
export function scoreAtMinute(allEvents, homeTeamId, minute) {
    let home = 0;
    let away = 0;
    for (const e of allEvents) {
        if (e.minute > minute) continue;
        if (e.type === 'goal') {
            if (e.teamId === homeTeamId) home++; else away++;
        } else if (e.type === 'own_goal') {
            // Own goals count for the opposing team
            if (e.teamId === homeTeamId) away++; else home++;
        }
    }
    return { home, away };
}

/**
 * Count events of given types for a team in a minute range.
 */
function countEventsInRange(allEvents, teamId, types, minMinute, maxMinute) {
    let count = 0;
    for (const e of allEvents) {
        if (e.minute >= minMinute && e.minute <= maxMinute && e.teamId === teamId && types.includes(e.type)) {
            count++;
        }
    }
    return count;
}

/**
 * Build article-aware name forms for a team.
 * article can be 'el', 'la', or null (no article).
 *
 * Returns: { name, el, del, al }
 *   el:  "el Real Madrid" / "la Real Sociedad" / "Osasuna"
 *   del: "del Real Madrid" / "de la Real Sociedad" / "de Osasuna"
 *   al:  "al Real Madrid" / "a la Real Sociedad" / "a Osasuna"
 */
export function buildTeamForms(name, article) {
    if (article === 'la') {
        return { name, el: 'la ' + name, del: 'de la ' + name, al: 'a la ' + name };
    }
    if (!article) {
        return { name, el: name, del: 'de ' + name, al: 'a ' + name };
    }
    // default: 'el'
    return { name, el: 'el ' + name, del: 'del ' + name, al: 'al ' + name };
}

/**
 * Generate contextual narrative events that react to the match state.
 * Placed at ~15-minute intervals throughout regular time.
 */
export function generateContextualNarratives(config) {
    const {
        homeTeamId, awayTeamId, homeTeamName, awayTeamName,
        homeArticle, awayArticle,
        venueName, narrativeTemplates, allEvents,
        isKnockout, isTwoLeggedTie,
        isNeutralVenue,
        firstHalfStoppage,
    } = config;

    const venue = venueName || '';
    const noVenue = !venueName;
    const hasHomeAdvantage = !isNeutralVenue;
    const homeForms = buildTeamForms(homeTeamName, homeArticle);
    const awayForms = buildTeamForms(awayTeamName, awayArticle);
    const events = [];
    const usedMinutes = new Set();
    for (const e of allEvents) usedMinutes.add(e.minute);

    // Replacements must be ordered longest-first so e.g. ':del_home' is matched
    // before ':home'. pickNarrative uses replaceAll which handles this correctly
    // as long as we list longer keys first in the object.
    const replacements = {
        ':del_home': homeForms.del,
        ':del_away': awayForms.del,
        ':al_home': homeForms.al,
        ':al_away': awayForms.al,
        ':el_home': homeForms.el,
        ':el_away': awayForms.el,
        ':home': homeForms.name,
        ':away': awayForms.name,
        ':venue': venue,
    };

    // Checkpoint minutes: ~15, ~30, ~46 (second half start), ~60, ~75, ~85
    const checkpoints = [
        { minute: 15, type: 'mid' },
        { minute: 30, type: 'mid' },
        { minute: 46, type: 'second_half_start' },
        { minute: 60, type: 'mid' },
        { minute: 75, type: 'mid' },
        { minute: 85, type: 'end' },
    ];

    // Second-half-start narrative is placed just past the end of 1H stoppage
    // so the simulator's clock tick reveals it the moment 2H starts (not
    // during 1H stoppage, where "game resumed" would feel premature). It
    // also carries phase='second_half' + displayMinute="45'" so event-feed
    // partitions it into the 2H bucket regardless of `firstHalfStoppage`.
    const fhs = firstHalfStoppage ?? 0;
    const secondHalfStartMinute = MINUTE.FIRST_HALF_END + fhs + 0.1;

    for (const cp of checkpoints) {
        const m = cp.type === 'second_half_start'
            ? secondHalfStartMinute
            : uniqueMinute(usedMinutes, cp.minute, cp.minute + 3);
        const score = scoreAtMinute(allEvents, homeTeamId, m);
        const scoreStr = `${score.home}-${score.away}`;

        // Count recent activity (last 15 minutes) for dominance detection
        const lookback = 15;
        const shotTypes = ['shot_on_target', 'shot_off_target', 'goal'];
        const homeShots = countEventsInRange(allEvents, homeTeamId, shotTypes, m - lookback, m);
        const awayShots = countEventsInRange(allEvents, awayTeamId, shotTypes, m - lookback, m);

        let templateKey = null;
        // Build replacements with longer keys first to avoid substring collisions
        let extraReplacements = { ...replacements, ':score': scoreStr };

        if (cp.type === 'second_half_start') {
            templateKey = 'contextualSecondHalfStart';
        } else if (cp.type === 'end') {
            // Late-game narratives based on score
            if (score.home > score.away) {
                templateKey = 'contextualEndWinning';
                extraReplacements = {
                    ':del_leading': homeForms.del, ':del_trailing': awayForms.del,
                    ':al_leading': homeForms.al, ':al_trailing': awayForms.al,
                    ':el_leading': homeForms.el, ':el_trailing': awayForms.el,
                    ':leading': homeForms.name, ':trailing': awayForms.name,
                    ...extraReplacements,
                };
                // 50% chance to show the losing team's perspective instead
                if (Math.random() < 0.5) {
                    const deficit = score.home - score.away;
                    templateKey = deficit === 1 ? 'contextualEndLosingByOne' : 'contextualEndLosing';
                }
            } else if (score.away > score.home) {
                templateKey = 'contextualEndWinning';
                extraReplacements = {
                    ':del_leading': awayForms.del, ':del_trailing': homeForms.del,
                    ':al_leading': awayForms.al, ':al_trailing': homeForms.al,
                    ':el_leading': awayForms.el, ':el_trailing': homeForms.el,
                    ':leading': awayForms.name, ':trailing': homeForms.name,
                    ...extraReplacements,
                };
                // 50% chance to show the losing team's perspective instead
                if (Math.random() < 0.5) {
                    const deficit = score.away - score.home;
                    templateKey = deficit === 1 ? 'contextualEndLosingByOne' : 'contextualEndLosing';
                }
            } else {
                // Tied at minute 85. Select pool by competition type.
                if (isKnockout && isTwoLeggedTie) {
                    // Aggregate across two legs (plus away goals / first-leg
                    // result) determines the winner — a draw here is not
                    // inherently "heading to extra time". Stay silent; the
                    // null templateKey is handled by the guard below.
                    templateKey = null;
                } else if (isKnockout) {
                    // Single-leg knockout: a draw leads to extra time.
                    templateKey = 'contextualEndDrawKnockout';
                } else {
                    // League / regular phase: a draw yields a point each.
                    templateKey = 'contextualEndDraw';
                }
            }
        } else {
            // Mid-game: pick based on state
            const dominant = homeShots >= awayShots + 3 ? 'home' : awayShots >= homeShots + 3 ? 'away' : null;

            if (dominant === 'home') {
                templateKey = 'contextualHomeDominant';
            } else if (dominant === 'away') {
                templateKey = 'contextualAwayDominant';
            } else if (score.home === score.away && score.home === 0) {
                templateKey = 'contextualDrawOpen';
            } else if (score.home === score.away) {
                templateKey = 'contextualDrawWithGoals';
            } else if (score.home > score.away) {
                templateKey = 'contextualHomeLeading';
            } else {
                templateKey = 'contextualAwayLeading';
            }

            // Occasionally inject fan/crowd narrative instead (~25% chance).
            // Skip at neutral venues — the home/away fans pools assume a
            // home crowd and travelling support, which doesn't apply when
            // neither team has home-field advantage (World Cup, neutral cup
            // finals, etc.).
            if (hasHomeAdvantage && Math.random() < 0.25) {
                templateKey = Math.random() < 0.5 ? 'contextualHomeFans' : 'contextualAwayFans';
            }
        }

        // Combine base pool with the home-advantage-only variant when the
        // home team actually plays at their own ground. At neutral venues,
        // only the base (venue-agnostic) lines are eligible.
        const baseTemplates = narrativeTemplates[templateKey] || [];
        const homeOnlyKey = templateKey + 'HomeOnly';
        const homeOnlyTemplates = hasHomeAdvantage
            ? (narrativeTemplates[homeOnlyKey] || [])
            : [];
        const templates = homeOnlyTemplates.length
            ? [...baseTemplates, ...homeOnlyTemplates]
            : baseTemplates;
        if (!templates.length) continue;

        const narrative = pickNarrative(templates, extraReplacements, { excludeVenue: noVenue });
        if (!narrative) continue;

        const event = {
            minute: m,
            displayMinute: formatAtmosphereDisplayMinute(m, config),
            type: 'contextual',
            atmosphere: true,
            playerName: '',
            teamId: null,
            gamePlayerId: null,
            metadata: { narrative },
        };

        // Tag the second-half-start narrative so event-feed buckets it
        // into the 2H section (its absolute minute = 45+fhs+0.1 would
        // otherwise fall into the 1H-stoppage range when fhs > 0). The
        // displayed label stays as "45'" to match the convention used
        // for half-time substitutions persisted at base=45 — override
        // the stoppage-formatted default ("45+N'") that would otherwise
        // come out of formatAtmosphereDisplayMinute.
        if (cp.type === 'second_half_start') {
            event.phase = 'second_half';
            event.displayMinute = `${MINUTE.FIRST_HALF_END}'`;
        }

        events.push(event);
    }

    return events;
}

/**
 * Generate tactical narrative events based on both teams' tactical setups.
 * These make tactical choices visible by commenting on their effects during the match.
 *
 * @param {Object} config - Must include tactics object with user/opponent setup
 * @returns {Array} Tactical narrative events placed at specific checkpoints
 */
export function generateTacticalNarratives(config) {
    const {
        homeTeamId, homeTeamName, awayTeamName,
        homeArticle, awayArticle,
        narrativeTemplates, allEvents, userTeamId, tactics,
    } = config;

    if (!tactics || !narrativeTemplates) return [];

    const events = [];
    const usedMinutes = new Set();
    for (const e of allEvents) usedMinutes.add(e.minute);

    const isUserHome = userTeamId === homeTeamId;
    const userTeamName = isUserHome ? homeTeamName : awayTeamName;
    const oppTeamName = isUserHome ? awayTeamName : homeTeamName;
    const userForms = buildTeamForms(userTeamName, isUserHome ? homeArticle : awayArticle);
    const oppForms = buildTeamForms(oppTeamName, isUserHome ? awayArticle : homeArticle);

    const replacements = {
        ':del_user': userForms.del,
        ':del_opp': oppForms.del,
        ':al_user': userForms.al,
        ':al_opp': oppForms.al,
        ':el_user': userForms.el,
        ':el_opp': oppForms.el,
        ':user': userForms.name,
        ':opp': oppForms.name,
    };

    // Tactical checkpoints: early (showing initial effect), mid (pressing fade), late (energy)
    const checkpoints = [
        { minute: 20, type: 'early' },
        { minute: 55, type: 'fade' },
        { minute: 75, type: 'late' },
    ];

    for (const cp of checkpoints) {
        // ~40% chance of generating a tactical narrative at each checkpoint
        if (Math.random() > 0.40) continue;

        const m = uniqueMinute(usedMinutes, cp.minute, cp.minute + 3);
        let templateKey = null;

        if (cp.type === 'early') {
            // Early-game: comment on the initial tactical setup
            if (tactics.userPressing === 'high_press') {
                templateKey = 'tacticalHighPressWorking';
            } else if (tactics.userPressing === 'low_block' && tactics.userDefLine === 'deep') {
                templateKey = 'tacticalLowBlockWall';
            } else if (tactics.userPlayingStyle === 'possession') {
                templateKey = 'tacticalPossessionControl';
            } else if (tactics.userPlayingStyle === 'counter_attack') {
                templateKey = 'tacticalCounterWaiting';
            } else if (tactics.userPlayingStyle === 'direct') {
                templateKey = 'tacticalDirectPlay';
            }
        } else if (cp.type === 'fade') {
            // Mid-game: pressing fade or interaction effects
            if (tactics.userPressing === 'high_press') {
                templateKey = 'tacticalHighPressFading';
            } else if (tactics.opponentPressing === 'high_press') {
                templateKey = 'tacticalOppPressFading';
            } else if (tactics.userPlayingStyle === 'possession' && tactics.opponentPressing === 'low_block' && tactics.opponentDefLine === 'deep') {
                templateKey = 'tacticalPossessionFrustrated';
            } else if (tactics.userPlayingStyle === 'direct' && tactics.opponentPressing === 'high_press') {
                templateKey = 'tacticalDirectBypassingPress';
            }
        } else if (cp.type === 'late') {
            // Late-game: energy and tactical consequences
            if (tactics.userPressing === 'high_press') {
                templateKey = 'tacticalHighPressExhausted';
            } else if (tactics.opponentPressing === 'high_press') {
                templateKey = 'tacticalOppExhausted';
            } else if (tactics.userPressing === 'low_block') {
                templateKey = 'tacticalLowBlockFresh';
            } else if (tactics.userPlayingStyle === 'counter_attack' && (tactics.opponentMentality === 'attacking' || tactics.opponentDefLine === 'high_line')) {
                templateKey = 'tacticalCounterExploiting';
            }
        }

        if (!templateKey) continue;

        const templates = narrativeTemplates[templateKey];
        if (!templates || !templates.length) continue;

        const narrative = pickNarrative(templates, replacements);
        if (!narrative) continue;

        events.push({
            minute: m,
            displayMinute: formatAtmosphereDisplayMinute(m, config),
            type: 'contextual',
            atmosphere: true,
            playerName: '',
            teamId: null,
            gamePlayerId: null,
            metadata: { narrative },
        });
    }

    return events;
}

/**
 * Generate all atmosphere events for regular time (both halves).
 *
 * @param {Object} config - Same as generateAtmosphereForPeriod, minus minMinute/maxMinute
 * @returns {Array} All atmosphere events for minutes 1-90
 */
export function generateRegularTimeAtmosphere(config) {
    const firstHalf = generateAtmosphereForPeriod({ ...config, minMinute: 1, maxMinute: 45 });
    const secondHalf = generateAtmosphereForPeriod({ ...config, minMinute: 46, maxMinute: 90 });
    const contextual = generateContextualNarratives(config);
    return [...firstHalf, ...secondHalf, ...contextual];
}

/**
 * Generate atmosphere events for extra time (both halves).
 *
 * @param {Object} config - Same shape, but allEvents should include regular-time events
 * @returns {Array} Atmosphere events for minutes 91-120
 */
export function generateExtraTimeAtmosphere(config) {
    const etFirst = generateAtmosphereForPeriod({ ...config, minMinute: 91, maxMinute: 105 });
    const etSecond = generateAtmosphereForPeriod({ ...config, minMinute: 106, maxMinute: 120 });
    // Contextual narratives are only generated for regular time (checkpoints don't apply to ET)
    return [...etFirst, ...etSecond];
}

/**
 * Add narrative commentary to goal events that come from the server.
 * Mutates the events array in place.
 */
export function addGoalNarratives(events, config) {
    const {
        homeTeamId, homeTeamName, awayTeamName,
        homeArticle, awayArticle, narrativeTemplates,
        userTeamId, tactics,
    } = config;

    const assistedTemplates = narrativeTemplates.goalAssisted || [];
    const soloTemplates = narrativeTemplates.goalSolo || [];
    const penaltyTemplates = narrativeTemplates.goalPenalty || [];
    const prefixTemplates = narrativeTemplates.goalPrefix || [];
    if (!assistedTemplates.length && !soloTemplates.length) return;

    const homeForms = buildTeamForms(homeTeamName, homeArticle);
    const awayForms = buildTeamForms(awayTeamName, awayArticle);

    // Map playing styles to tactical goal template keys
    const tacticalGoalTemplates = {
        counter_attack: narrativeTemplates.goalCounterAttack || [],
        possession: narrativeTemplates.goalPossession || [],
        direct: narrativeTemplates.goalDirect || [],
    };

    // Determine which playing style each team uses
    const isUserHome = userTeamId === homeTeamId;
    const homeStyle = tactics ? (isUserHome ? tactics.userPlayingStyle : tactics.opponentPlayingStyle) : null;
    const awayStyle = tactics ? (isUserHome ? tactics.opponentPlayingStyle : tactics.userPlayingStyle) : null;

    for (const event of events) {
        if (event.type !== 'goal' || event.narrative) continue;

        const isHome = event.teamId === homeTeamId;
        const teamForms = isHome ? homeForms : awayForms;
        const scoringStyle = isHome ? homeStyle : awayStyle;

        // Penalty goals always use penalty-specific templates
        const isPenalty = event.metadata?.is_penalty && penaltyTemplates.length > 0;

        // ~50% chance to use tactical template when a non-balanced style is active
        const tacticalTemplates = scoringStyle ? (tacticalGoalTemplates[scoringStyle] || []) : [];
        const useTactical = !isPenalty && tacticalTemplates.length > 0 && Math.random() < 0.5;

        let templates;
        if (isPenalty) {
            templates = penaltyTemplates;
        } else if (useTactical) {
            templates = tacticalTemplates;
        } else {
            templates = event.assistPlayerName ? assistedTemplates : soloTemplates;
        }

        const replacements = {
            ':del_team': teamForms.del,
            ':el_team': teamForms.el,
            ':player': event.playerName,
            ':team': teamForms.name,
        };

        // Prefix each goal narrative with an emphatic opener ("¡GOLAZO del ...!",
        // "GOAL for ...!", etc.) so goal events read with punch in the feed.
        const prefix = pickNarrative(prefixTemplates, replacements);
        const body = pickNarrative(templates, replacements);
        event.narrative = prefix ? `${prefix} ${body}` : body;
    }
}

