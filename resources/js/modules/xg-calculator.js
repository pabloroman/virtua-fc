/**
 * Pure xG preview calculator.
 *
 * Replicates the MatchSimulator formula for pre-match expected goals estimation.
 * Takes all tactical selections and team strengths, returns user/opponent xG.
 *
 * @param {Object} params
 * @param {number} params.userAvg - User team average overall rating
 * @param {number} params.opponentAvg - Opponent team average overall rating
 * @param {boolean} params.isHome - Whether user is the home team
 * @param {string} params.userFormation - User's selected formation
 * @param {string} params.userMentality - User's selected mentality
 * @param {string} params.userStyle - User's selected playing style
 * @param {string} params.userPressing - User's selected pressing
 * @param {string} params.userDefLine - User's selected defensive line
 * @param {string} params.opponentFormation - Predicted opponent formation
 * @param {string} params.opponentMentality - Predicted opponent mentality
 * @param {string} params.opponentStyle - Predicted opponent playing style
 * @param {string} params.opponentPressing - Predicted opponent pressing
 * @param {string} params.opponentDefLine - Predicted opponent defensive line
 * @param {Object} params.formationModifiers - { formation: { attack, defense } }
 * @param {Object} params.xgConfig - Server-provided xG calculation config
 * @param {number} params.userForwardPhysical - Best forward physical ability (for high-line nullification)
 * @returns {{ userXG: number, opponentXG: number } | null}
 */
export function calculateXgPreview({
    userAvg,
    opponentAvg,
    isHome,
    userFormation,
    userMentality,
    userStyle,
    userPressing,
    userDefLine,
    opponentFormation,
    opponentMentality,
    opponentStyle,
    opponentPressing,
    opponentDefLine,
    formationModifiers,
    xgConfig,
    userForwardPhysical,
}) {
    const cfg = xgConfig;
    if (!cfg || !userAvg || !opponentAvg) return null;

    // Normalize averages to 0-1 scale
    const userStrength = userAvg / 100;
    const oppStrength = opponentAvg / 100;

    // Determine home/away strength (user perspective)
    const homeStrength = isHome ? userStrength : oppStrength;
    const awayStrength = isHome ? oppStrength : userStrength;

    // Resolve tactical selections with defaults
    const userFm = userFormation;
    const userMent = userMentality;
    const oppFm = opponentFormation || '4-4-2';
    const oppMent = opponentMentality || 'balanced';
    const oppStyle = opponentStyle || 'balanced';
    const oppPress = opponentPressing || 'standard';
    const oppDefLn = opponentDefLine || 'normal';

    // Map to home/away perspective
    const homeFm = isHome ? userFm : oppFm;
    const awayFm = isHome ? oppFm : userFm;
    const homeMent = isHome ? userMent : oppMent;
    const awayMent = isHome ? oppMent : userMent;
    const homeStyle = isHome ? userStyle : oppStyle;
    const awayStyle = isHome ? oppStyle : userStyle;
    const homePress = isHome ? userPressing : oppPress;
    const awayPress = isHome ? oppPress : userPressing;
    const homeDefLn = isHome ? userDefLine : oppDefLn;
    const awayDefLn = isHome ? oppDefLn : userDefLine;

    // --- Base xG from strength ratio ---
    const strengthRatio = awayStrength > 0 ? homeStrength / awayStrength : 1.0;
    const ratioExp = cfg.ratio_exponent;
    const baseGoals = cfg.base_goals;
    const homeAdv = cfg.home_advantage_goals;

    const getFmMods = (fm) => formationModifiers[fm] || { attack: 1.0, defense: 1.0 };
    const homeFmMods = getFmMods(homeFm);
    const awayFmMods = getFmMods(awayFm);

    const homeMentMods = cfg.mentalities[homeMent] || { own_goals: 1.0, opponent_goals: 1.0 };
    const awayMentMods = cfg.mentalities[awayMent] || { own_goals: 1.0, opponent_goals: 1.0 };

    let homeXG = (Math.pow(strengthRatio, ratioExp) * baseGoals + homeAdv)
        * homeFmMods.attack
        * awayFmMods.defense
        * homeMentMods.own_goals
        * awayMentMods.opponent_goals;

    let awayXG = (Math.pow(1 / strengthRatio, ratioExp) * baseGoals)
        * awayFmMods.attack
        * homeFmMods.defense
        * awayMentMods.own_goals
        * homeMentMods.opponent_goals;

    // --- Playing Style modifiers ---
    const homeStyleMods = cfg.playing_styles[homeStyle] || { own_xg: 1.0, opp_xg: 1.0 };
    const awayStyleMods = cfg.playing_styles[awayStyle] || { own_xg: 1.0, opp_xg: 1.0 };
    homeXG *= homeStyleMods.own_xg;
    homeXG *= awayStyleMods.opp_xg;
    awayXG *= awayStyleMods.own_xg;
    awayXG *= homeStyleMods.opp_xg;

    // --- Pressing modifiers ---
    const homePressMods = cfg.pressing[homePress] || { opp_xg: 1.0 };
    const awayPressMods = cfg.pressing[awayPress] || { opp_xg: 1.0 };
    homeXG *= awayPressMods.opp_xg;
    awayXG *= homePressMods.opp_xg;

    // --- Defensive Line modifiers ---
    const homeDefMods = cfg.defensive_line[homeDefLn] || { own_xg: 1.0, opp_xg: 1.0, physical_threshold: 0 };
    const awayDefMods = cfg.defensive_line[awayDefLn] || { own_xg: 1.0, opp_xg: 1.0, physical_threshold: 0 };

    let homeDefOwn = homeDefMods.own_xg;
    let homeDefOpp = homeDefMods.opp_xg;
    let awayDefOwn = awayDefMods.own_xg;
    let awayDefOpp = awayDefMods.opp_xg;

    // High line nullification: check user's forward physical ability
    // (We don't have opponent individual player data in JS, so only check user's forwards)
    if (homeDefMods.physical_threshold > 0 && !isHome) {
        if (userForwardPhysical >= homeDefMods.physical_threshold) {
            homeDefOwn = 1.0;
            homeDefOpp = 1.0;
        }
    }
    if (awayDefMods.physical_threshold > 0 && isHome) {
        if (userForwardPhysical >= awayDefMods.physical_threshold) {
            awayDefOwn = 1.0;
            awayDefOpp = 1.0;
        }
    }

    homeXG *= homeDefOwn;
    awayXG *= homeDefOpp;
    awayXG *= awayDefOwn;
    homeXG *= awayDefOpp;

    // --- Tactical Interactions ---
    const interactions = cfg.tactical_interactions;

    // Counter-Attack vs opponent Attacking + High Line
    if (homeStyle === 'counter_attack' && awayMent === 'attacking' && awayDefLn === 'high_line') {
        homeXG *= interactions.counter_vs_attacking_high_line || 1.0;
    }
    if (awayStyle === 'counter_attack' && homeMent === 'attacking' && homeDefLn === 'high_line') {
        awayXG *= interactions.counter_vs_attacking_high_line || 1.0;
    }

    // Possession disrupted by opponent High Press
    if (homeStyle === 'possession' && awayPress === 'high_press') {
        homeXG *= interactions.possession_disrupted_by_high_press || 1.0;
    }
    if (awayStyle === 'possession' && homePress === 'high_press') {
        awayXG *= interactions.possession_disrupted_by_high_press || 1.0;
    }

    // Direct bypasses opponent High Press
    if (homeStyle === 'direct' && awayPress === 'high_press') {
        homeXG *= interactions.direct_bypasses_high_press || 1.0;
    }
    if (awayStyle === 'direct' && homePress === 'high_press') {
        awayXG *= interactions.direct_bypasses_high_press || 1.0;
    }

    // Return from user perspective
    const userXG = isHome ? homeXG : awayXG;
    const opponentXG = isHome ? awayXG : homeXG;

    return {
        userXG: Math.round(userXG * 100) / 100,
        opponentXG: Math.round(opponentXG * 100) / 100,
    };
}
