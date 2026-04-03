/**
 * Client-side atmosphere event generator.
 *
 * Generates cosmetic match events (shots on/off target, fouls) and narrative
 * text for substitutions/injuries. These events are purely decorative and
 * don't affect match outcomes — they exist to make the live match feed
 * feel more like real football commentary.
 *
 * @module atmosphere-generator
 */

// Position-group weights for shot attribution (forwards shoot more)
const SHOT_WEIGHTS = {
    FW: 25,
    AM: 12,
    MF: 6,
    DF: 3,
    GK: 0,
};

// Position-group weights for foul attribution (defenders foul more)
const FOUL_WEIGHTS = {
    DF: 20,
    MF: 12,
    AM: 6,
    FW: 4,
    GK: 0,
};

// Tuning constants (cosmetic only — no gameplay impact)
const SHOTS_PER_XG = 4.0;
const ON_TARGET_RATIO = 0.38;
const FOULS_BASE_PER_TEAM = 3.0;
const XG_BASELINE = 1.2; // added to score as xG proxy

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
        if (e.type === 'red_card') {
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
                positionGroup: 'MF', // default — we don't know sub's position
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
        const w = weights[p.positionGroup] || 1;
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
function pickNarrative(templates, replacements) {
    if (!templates || !templates.length) return '';
    let text = templates[Math.floor(Math.random() * templates.length)];
    for (const [placeholder, value] of Object.entries(replacements)) {
        text = text.replaceAll(placeholder, value);
    }
    return text;
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
        homePlayers, awayPlayers,
        homeScore, awayScore,
        narrativeTemplates, allEvents,
        minMinute, maxMinute,
    } = config;

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

    const teams = [
        {
            id: homeTeamId, name: homeTeamName, opponentName: awayTeamName,
            players: homePlayers, score: homeScore,
        },
        {
            id: awayTeamId, name: awayTeamName, opponentName: homeTeamName,
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
            const available = getAvailablePlayers(team.players, allEvents, minute, team.id);
            const player = pickWeightedPlayer(available, SHOT_WEIGHTS);
            if (!player) continue;

            events.push({
                minute,
                type: 'shot_on_target',
                playerName: player.name,
                teamId: team.id,
                gamePlayerId: player.id || null,
                metadata: {
                    narrative: pickNarrative(narrativeTemplates.shotOnTarget || [], {
                        ':player': player.name,
                        ':team': team.name,
                        ':opponent': team.opponentName,
                    }),
                },
            });
        }

        for (let i = 0; i < offTarget; i++) {
            const minute = uniqueMinute(usedMinutes, minMinute, maxMinute);
            const available = getAvailablePlayers(team.players, allEvents, minute, team.id);
            const player = pickWeightedPlayer(available, SHOT_WEIGHTS);
            if (!player) continue;

            events.push({
                minute,
                type: 'shot_off_target',
                playerName: player.name,
                teamId: team.id,
                gamePlayerId: player.id || null,
                metadata: {
                    narrative: pickNarrative(narrativeTemplates.shotOffTarget || [], {
                        ':player': player.name,
                        ':team': team.name,
                        ':opponent': team.opponentName,
                    }),
                },
            });
        }
    }

    // --- Fouls ---
    for (const team of teams) {
        const variation = (Math.random() * 2 - 1); // -1 to +1
        const fouls = Math.max(0, Math.round((FOULS_BASE_PER_TEAM + variation) * matchFraction));

        for (let i = 0; i < fouls; i++) {
            const minute = uniqueMinute(usedMinutes, minMinute, maxMinute);
            const available = getAvailablePlayers(team.players, allEvents, minute, team.id);
            const player = pickWeightedPlayer(available, FOUL_WEIGHTS);
            if (!player) continue;

            events.push({
                minute,
                type: 'foul',
                playerName: player.name,
                teamId: team.id,
                gamePlayerId: player.id || null,
                metadata: {
                    narrative: pickNarrative(narrativeTemplates.foul || [], {
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
 * Generate all atmosphere events for regular time (both halves).
 *
 * @param {Object} config - Same as generateAtmosphereForPeriod, minus minMinute/maxMinute
 * @returns {Array} All atmosphere events for minutes 1-90
 */
export function generateRegularTimeAtmosphere(config) {
    const firstHalf = generateAtmosphereForPeriod({ ...config, minMinute: 1, maxMinute: 45 });
    const secondHalf = generateAtmosphereForPeriod({ ...config, minMinute: 46, maxMinute: 90 });
    return [...firstHalf, ...secondHalf];
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
    return [...etFirst, ...etSecond];
}

/**
 * Add narrative text to substitution and injury events that come
 * from the server without narratives.
 *
 * @param {Array} events - Mutable array of event objects
 * @param {Object} config
 * @param {string} config.homeTeamId
 * @param {string} config.homeTeamName
 * @param {string} config.awayTeamName
 * @param {Object} config.narrativeTemplates - {substitution:[], injury:[]}
 */
export function addNarrativesToEvents(events, config) {
    const { homeTeamId, homeTeamName, awayTeamName, narrativeTemplates } = config;

    for (const event of events) {
        if (event.type === 'injury' && !event.narrative) {
            const teamName = event.teamId === homeTeamId ? homeTeamName : awayTeamName;
            event.narrative = pickNarrative(narrativeTemplates.injury || [], {
                ':player': event.playerName,
                ':team': teamName,
            });
        }

        if (event.type === 'substitution' && !event.narrative) {
            const teamName = event.teamId === homeTeamId ? homeTeamName : awayTeamName;
            event.narrative = pickNarrative(narrativeTemplates.substitution || [], {
                ':player_in': event.playerInName || '',
                ':player_out': event.playerName || '',
                ':team': teamName,
            });
        }
    }
}
