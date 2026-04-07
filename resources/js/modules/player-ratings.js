/**
 * Client-side player match rating calculator.
 *
 * Replicates the MVP scoring formula from MatchdayOrchestrator::calculateMvp()
 * to compute per-player ratings on a 1–10 scale from raw performance modifiers
 * and match events.
 *
 * @see app/Modules/Match/Services/MatchdayOrchestrator.php
 */

// Position-scaled bonuses (rarer contributions score higher)
const GOAL_BONUSES = { Goalkeeper: 0.55, Defender: 0.45, Midfielder: 0.35, Forward: 0.30 };
const ASSIST_BONUSES = { Goalkeeper: 0.25, Defender: 0.15, Midfielder: 0.15, Forward: 0.15 };

/**
 * Map a position group abbreviation to its full name used in the scoring formula.
 */
function resolvePositionGroup(abbr) {
    switch (abbr) {
        case 'GK': return 'Goalkeeper';
        case 'DEF': return 'Defender';
        case 'MID': return 'Midfielder';
        case 'FWD': return 'Forward';
        default: return 'Midfielder';
    }
}

/**
 * Count relevant events per player from the events array.
 * Events have: { type, gamePlayerId, teamId }
 * Goal events may include assistPlayerId for the assisting player.
 * (Assist events are paired with goals in formatMatchEvents, not separate entries.)
 */
export function countEvents(events) {
    const goals = {};
    const assists = {};
    const yellowCards = {};
    const redCards = {};

    for (const event of events) {
        const id = event.gamePlayerId;
        if (!id) continue;

        switch (event.type) {
            case 'goal':
                goals[id] = (goals[id] || 0) + 1;
                if (event.assistPlayerId) {
                    assists[event.assistPlayerId] = (assists[event.assistPlayerId] || 0) + 1;
                }
                break;
            case 'yellow_card':
                yellowCards[id] = (yellowCards[id] || 0) + 1;
                break;
            case 'red_card':
                redCards[id] = (redCards[id] || 0) + 1;
                break;
        }
    }

    return { goals, assists, yellowCards, redCards };
}

/**
 * Build lookup maps for substitutions from match events.
 *
 * @param {Array} events - Match events array
 * @returns {{ subbedOut: Object, subbedIn: Object }}
 *   subbedOut: { [gamePlayerId]: { minute, replacedByName, replacedById } }
 *   subbedIn:  { [playerInId]: { minute, replacedName, name, teamId } }
 */
export function buildSubstitutionMap(events) {
    const subbedOut = {};
    const subbedIn = {};

    for (const e of events) {
        if (e.type !== 'substitution') continue;

        const outId = e.gamePlayerId;
        const inId = e.metadata?.player_in_id;

        if (outId) {
            subbedOut[outId] = {
                minute: e.minute,
                replacedByName: e.playerInName || '',
                replacedById: inId || null,
            };
        }
        if (inId) {
            subbedIn[inId] = {
                minute: e.minute,
                replacedName: e.playerName || '',
                name: e.playerInName || '',
                teamId: e.teamId,
            };
        }
    }

    return { subbedOut, subbedIn };
}

/**
 * Calculate match ratings for all players that have performance data.
 *
 * @param {Array} homeRoster  - Home team lineup roster (each has: id, positionGroup, performance)
 * @param {Array} awayRoster  - Away team lineup roster
 * @param {Array} events      - Match events with type, gamePlayerId, teamId
 * @param {number} homeScore  - Final home score
 * @param {number} awayScore  - Final away score
 * @param {string} homeTeamId - Home team ID
 * @param {string} awayTeamId - Away team ID
 * @param {Array} [subsIn=[]] - Additional sub-in players: { id, performance, positionGroup, teamId }
 * @returns {Object} Map of playerId → rating (1.0–10.0)
 */
export function calculatePlayerRatings(homeRoster, awayRoster, events, homeScore, awayScore, homeTeamId, awayTeamId, subsIn = []) {
    const ratings = {};
    const { goals, assists, yellowCards, redCards } = countEvents(events);

    const winningTeamId = homeScore > awayScore ? homeTeamId
        : awayScore > homeScore ? awayTeamId
        : null;

    const goalsConceded = {
        [homeTeamId]: awayScore,
        [awayTeamId]: homeScore,
    };

    const allPlayers = [
        ...homeRoster.map(p => ({ ...p, teamId: homeTeamId })),
        ...awayRoster.map(p => ({ ...p, teamId: awayTeamId })),
        ...subsIn,
    ];

    for (const player of allPlayers) {
        if (player.performance == null) continue;

        const group = resolvePositionGroup(player.positionGroup);
        const teamConceded = goalsConceded[player.teamId] || 0;

        // Normalized performance: map 0.70–1.30 to 0.0–1.0
        let score = (player.performance - 0.70) / 0.60;

        // Position-scaled goal/assist bonuses
        score += (goals[player.id] || 0) * (GOAL_BONUSES[group] || 0.15);
        score += (assists[player.id] || 0) * (ASSIST_BONUSES[group] || 0.10);

        // Card penalties
        score -= (yellowCards[player.id] || 0) * 0.10;
        score -= (redCards[player.id] || 0) * 0.30;

        // Clean sheet bonus for goalkeepers and defenders
        if (teamConceded === 0) {
            if (group === 'Goalkeeper') score += 0.20;
            else if (group === 'Defender') score += 0.15;
        } else if (teamConceded === 1) {
            if (group === 'Goalkeeper') score += 0.05;
            else if (group === 'Defender') score += 0.05;
        }

        // Goals conceded penalty for goalkeepers
        if (group === 'Goalkeeper') {
            if (teamConceded >= 4) score -= 0.20;
            else if (teamConceded >= 3) score -= 0.10;
        }

        // Winning team edge
        if (winningTeamId && player.teamId === winningTeamId) {
            score += 0.08;
        }

        // Goals against penalty for losing team (linear per goal conceded)
        const losingTeamId = homeScore !== awayScore
            ? (homeScore < awayScore ? homeTeamId : awayTeamId)
            : null;
        if (losingTeamId && player.teamId === losingTeamId) {
            score -= Math.min(teamConceded * 0.04, 0.20);
        }

        // Convert to 1–10 scale
        ratings[player.id] = Math.round(Math.max(1, Math.min(10, score * 4 + 5)) * 10) / 10;
    }

    return ratings;
}

/**
 * Return CSS classes for a rating value's color tier.
 *
 * @param {number} rating - Rating on 1–10 scale
 * @returns {string} Tailwind CSS classes
 */
export function ratingColor(rating) {
    if (rating >= 8.0) return 'bg-accent-green/20 text-accent-green';
    if (rating >= 7.0) return 'bg-accent-blue/20 text-accent-blue';
    if (rating >= 6.0) return 'bg-surface-700 text-text-secondary';
    if (rating >= 5.0) return 'bg-accent-orange/20 text-accent-orange';
    return 'bg-accent-red/20 text-accent-red';
}

/**
 * Merge new performance values into roster arrays.
 *
 * @param {Array} homeRoster       - Home team roster
 * @param {Array} awayRoster       - Away team roster
 * @param {Object} newPerformances - Map of playerId → performance modifier
 */
export function updateRosterPerformances(homeRoster, awayRoster, newPerformances) {
    for (const roster of [homeRoster, awayRoster]) {
        for (const player of roster) {
            if (newPerformances[player.id] !== undefined) {
                player.performance = newPerformances[player.id];
            }
        }
    }
}
