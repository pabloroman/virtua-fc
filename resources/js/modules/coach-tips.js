/**
 * Pure coach tips generator.
 *
 * Analyzes the current lineup, tactical selections, and opponent data to
 * produce prioritized coaching recommendations.
 *
 * @param {Object} params
 * @param {Array} params.selectedPlayers - Array of selected player IDs
 * @param {Object} params.playersData - Map of player ID → player data
 * @param {Object} params.translations - Translation strings
 * @param {number} params.opponentAverage - Opponent team average
 * @param {number} params.teamAverage - User team average (from selected players)
 * @param {number} params.userTeamAverage - Fallback user team average
 * @param {string|null} params.opponentFormation - Predicted opponent formation
 * @param {string|null} params.opponentMentality - Predicted opponent mentality
 * @param {string} params.selectedFormation - User's selected formation
 * @param {string} params.selectedMentality - User's selected mentality
 * @param {Object} params.formationModifiers - { formation: { attack, defense } }
 * @param {boolean} params.isHome - Whether user is the home team
 * @returns {Array<{id: string, priority: number, type: string, message: string}>}
 */
export function generateCoachTips({
    selectedPlayers,
    playersData,
    translations,
    opponentAverage,
    teamAverage,
    userTeamAverage,
    opponentFormation,
    opponentMentality,
    selectedFormation,
    selectedMentality,
    formationModifiers,
    isHome,
}) {
    const tips = [];
    const t = translations;
    const oppAvg = opponentAverage;
    const userAvg = teamAverage || userTeamAverage;
    const diff = userAvg - oppAvg;
    const isWeaker = oppAvg > 0 && diff <= -5;
    const isStronger = oppAvg > 0 && diff >= 5;
    const fmMods = formationModifiers[selectedFormation];
    const oppFm = opponentFormation;
    const oppMent = opponentMentality;
    const oppMentLabel = oppMent ? (t['mentality_' + oppMent] || oppMent) : '';

    // Opponent tactical tips (using predicted formation/mentality)
    if (oppMent === 'defensive' && oppFm) {
        tips.push({ id: 'opp_defensive', priority: 1, type: 'info', message: t.coach_opponent_defensive_setup.replace(':formation', oppFm).replace(':mentality', oppMentLabel) });
    } else if (oppMent === 'attacking' && oppFm) {
        tips.push({ id: 'opp_attacking', priority: 1, type: 'info', message: t.coach_opponent_attacking_setup.replace(':formation', oppFm).replace(':mentality', oppMentLabel) });
    } else if (isWeaker && selectedMentality !== 'defensive') {
        tips.push({ id: 'defensive_recommended', priority: 1, type: 'warning', message: t.coach_defensive_recommended });
    }
    if (isStronger && selectedMentality !== 'attacking' && oppMent !== 'attacking') {
        tips.push({ id: 'attacking_vs_weaker', priority: 4, type: 'info', message: t.coach_attacking_recommended });
    }
    if (isWeaker && fmMods && fmMods.attack > 1.0) {
        tips.push({ id: 'risky_formation', priority: 2, type: 'warning', message: t.coach_risky_formation });
    }
    // Opponent playing 5-at-the-back
    if (oppFm && (oppFm.startsWith('5-') || oppFm === '5-3-2' || oppFm === '5-4-1')) {
        tips.push({ id: 'opp_deep_block', priority: 3, type: 'info', message: t.coach_opponent_deep_block });
    }

    // Fitness tips (only if lineup has players)
    if (selectedPlayers.length > 0) {
        const criticalNames = [];
        let lowFitnessCount = 0;
        selectedPlayers.forEach(id => {
            const p = playersData[id];
            if (!p) return;
            if (p.fitness < 50) criticalNames.push(p.name);
            else if (p.fitness < 70) lowFitnessCount++;
        });
        if (criticalNames.length > 0) {
            tips.push({ id: 'critical_fitness', priority: 0, type: 'warning', message: t.coach_critical_fitness.replace(':names', criticalNames.join(', ')) });
        }
        if (lowFitnessCount > 0) {
            tips.push({ id: 'low_fitness', priority: 1, type: 'warning', message: t.coach_low_fitness.replace(':count', lowFitnessCount) });
        }

        // Morale tips
        let lowMoraleCount = 0;
        selectedPlayers.forEach(id => {
            const p = playersData[id];
            if (p && p.morale < 60) lowMoraleCount++;
        });
        if (lowMoraleCount > 0) {
            tips.push({ id: 'low_morale', priority: 2, type: 'warning', message: t.coach_low_morale.replace(':count', lowMoraleCount) });
        }
    }

    // Bench frustration: quality non-selected players losing morale
    const selectedSet = new Set(selectedPlayers);
    let benchFrustrationCount = 0;
    Object.values(playersData).forEach(p => {
        if (!selectedSet.has(p.id) && p.isAvailable && p.overallScore >= 70 && p.morale < 65) {
            benchFrustrationCount++;
        }
    });
    if (benchFrustrationCount >= 2) {
        tips.push({ id: 'bench_frustration', priority: 4, type: 'info', message: t.coach_bench_frustration.replace(':count', benchFrustrationCount) });
    }

    // Home advantage (low priority filler)
    if (isHome) {
        tips.push({ id: 'home_advantage', priority: 5, type: 'info', message: t.coach_home_advantage });
    }

    // Sort by priority (lower = more important), limit to 4
    tips.sort((a, b) => a.priority - b.priority);

    // If we have 4+ tips, drop home_advantage to make room for more important ones
    const filtered = tips.length > 4 ? tips.filter(tip => tip.id !== 'home_advantage') : tips;
    return filtered.slice(0, 4);
}
