/**
 * Match summary generator.
 *
 * Produces a sports-journalism-style paragraph summarizing the match result
 * at full time. Composed from template fragments stored in translation files,
 * selected based on match analysis (score, goal distribution, red cards,
 * comebacks, team form, competition context).
 *
 * @module match-summary-generator
 */

import { buildTeamForms } from './atmosphere-generator.js';

function pickTemplate(templates, replacements) {
    if (!templates || !templates.length) return '';
    // Tournament matches have no venue; drop templates that reference any
    // venue placeholder so we don't render awkward empty phrases.
    let pool = templates;
    const venueMissing = replacements
        && (replacements[':en_venue'] === '' || replacements[':en_venue'] === undefined)
        && (replacements[':venue'] === '' || replacements[':venue'] === undefined);
    if (venueMissing) {
        const filtered = templates.filter(t =>
            !t.includes(':en_venue') && !t.includes(':el_venue') && !t.includes(':del_venue') && !t.includes(':venue')
        );
        if (filtered.length) pool = filtered;
    }
    const text = pool[Math.floor(Math.random() * pool.length)];
    return applyReplacements(text, replacements);
}

function capitalizeSentences(text) {
    if (!text) return text;
    // Uppercase the first letter after sentence-starting positions:
    // start of string, sentence terminator + whitespace, or Spanish opening
    // "¡"/"¿". Skip any intervening punctuation/whitespace so "¡el_home" →
    // "¡El Real Madrid" rather than leaving the letter lowercase.
    return text.replace(/(^|[.!?]\s+|[¡¿])([\s¡¿]*)([\p{L}])/gu, (_, lead, between, ch) => {
        return lead + between + ch.toUpperCase();
    });
}

function applyReplacements(text, replacements) {
    let result = text;
    // Sort by length desc so longer placeholders (e.g. :scorer) are replaced
    // before shorter ones that are substrings of them (e.g. :score).
    const entries = Object.entries(replacements).sort((a, b) => b[0].length - a[0].length);
    for (const [placeholder, value] of entries) {
        result = result.replaceAll(placeholder, value);
    }
    return result;
}

function joinScorers(names, conjunction) {
    if (names.length === 0) return '';
    if (names.length === 1) return names[0];
    return names.slice(0, -1).join(', ') + ' ' + conjunction + ' ' + names[names.length - 1];
}

function teamPlaceholders(forms) {
    return {
        ':team': forms.name,
        ':el_team': forms.el,
        ':del_team': forms.del,
        ':al_team': forms.al,
    };
}

function countTrailingStreak(formArray, predicate) {
    let count = 0;
    for (let i = formArray.length - 1; i >= 0; i--) {
        if (predicate(formArray[i])) count++;
        else break;
    }
    return count;
}

function detectHatTrick(allEvents) {
    const tally = {};
    for (const e of allEvents) {
        if (e.type === 'goal' && e.playerName) {
            if (!tally[e.playerName]) {
                tally[e.playerName] = { count: 0, teamId: e.teamId };
            }
            tally[e.playerName].count++;
        }
    }
    let best = null;
    for (const [name, info] of Object.entries(tally)) {
        if (info.count >= 3 && (!best || info.count > best.count)) {
            best = { playerName: name, count: info.count, teamId: info.teamId };
        }
    }
    return best;
}

/**
 * Find the last goal that changed the result (winner or equalizer) at min >= 85.
 * Returns { event, wasWinner, wasEqualizer } or null.
 */
function detectLastMinuteGoal(allEvents, homeTeamId) {
    const goals = allEvents
        .filter(e => e.type === 'goal' || e.type === 'own_goal')
        .sort((a, b) => a.minute - b.minute);

    if (goals.length === 0) return null;

    const lastGoal = goals[goals.length - 1];
    if (lastGoal.minute < 85) return null;

    const goalsBefore = goals.slice(0, -1);
    let homeBefore = 0;
    let awayBefore = 0;
    for (const g of goalsBefore) {
        const scoringForHome = (g.type === 'goal' && g.teamId === homeTeamId)
            || (g.type === 'own_goal' && g.teamId !== homeTeamId);
        if (scoringForHome) homeBefore++;
        else awayBefore++;
    }

    const lastForHome = (lastGoal.type === 'goal' && lastGoal.teamId === homeTeamId)
        || (lastGoal.type === 'own_goal' && lastGoal.teamId !== homeTeamId);

    const homeAfter = homeBefore + (lastForHome ? 1 : 0);
    const awayAfter = awayBefore + (lastForHome ? 0 : 1);

    const wasTiedBefore = homeBefore === awayBefore;
    const wasLosingBefore = lastForHome ? homeBefore < awayBefore : awayBefore < homeBefore;
    const isWinnerAfter = homeAfter !== awayAfter;
    const isTiedAfter = homeAfter === awayAfter;

    if ((wasLosingBefore || wasTiedBefore) && isWinnerAfter) {
        return { event: lastGoal, wasWinner: true, wasEqualizer: false };
    }
    if (!wasTiedBefore && isTiedAfter) {
        return { event: lastGoal, wasWinner: false, wasEqualizer: true };
    }
    return null;
}

export function generateMatchSummary(config) {
    const {
        homeTeamId, awayTeamId,
        homeTeamName, awayTeamName,
        homeArticle, awayArticle,
        homeScore, awayScore,
        venueName,
        venueEnPhrase,
        venueElPhrase,
        venueDePhrase,
        narrativeTemplates: t,
        mvpPlayerName, mvpPlayerTeamId,
        hasExtraTime, etHomeScore, etAwayScore,
        penaltyResult,
        allEvents,
        isKnockout,
        isTwoLeggedTie,
        isSecondLeg,
        knockoutRoundNumber,
        competitionRole,
        competitionName,
        homeForm, awayForm,
        homePosition, awayPosition,
        tournamentResultType,
    } = config;

    const homeForms = buildTeamForms(homeTeamName, homeArticle);
    const awayForms = buildTeamForms(awayTeamName, awayArticle);

    const totalHome = homeScore + (etHomeScore || 0);
    const totalAway = awayScore + (etAwayScore || 0);

    let winnerId = null;
    let loserId = null;
    let winnerForms = null;
    let loserForms = null;

    if (penaltyResult) {
        winnerId = penaltyResult.home > penaltyResult.away ? homeTeamId : awayTeamId;
        loserId = winnerId === homeTeamId ? awayTeamId : homeTeamId;
    } else if (totalHome !== totalAway) {
        winnerId = totalHome > totalAway ? homeTeamId : awayTeamId;
        loserId = winnerId === homeTeamId ? awayTeamId : homeTeamId;
    }

    if (winnerId) {
        winnerForms = winnerId === homeTeamId ? homeForms : awayForms;
        loserForms = loserId === homeTeamId ? homeForms : awayForms;
    }

    const scoreStr = `${totalHome}-${totalAway}`;
    const regularScoreStr = `${homeScore}-${awayScore}`;
    const goalDiff = Math.abs(totalHome - totalAway);
    const totalGoals = totalHome + totalAway;
    const isDraw = totalHome === totalAway && !penaltyResult;
    const isGoalless = totalGoals === 0;

    const isBlowout = goalDiff >= 3;
    const isNarrowWin = goalDiff === 1 && !isDraw;
    const isCup = competitionRole === 'domestic_cup' || competitionRole === 'european';
    // A knockout match decides progression (single-leg tie, or second leg of
    // a two-legged tie). First legs and Swiss league-phase matches don't.
    const isKnockoutDecisive = !!isKnockout && (!isTwoLeggedTie || !!isSecondLeg);
    const isHighStakes = isKnockoutDecisive && knockoutRoundNumber && knockoutRoundNumber >= 5;
    const isChampion = tournamentResultType === 'champion';

    const hatTrick = detectHatTrick(allEvents);
    const lastMinuteGoal = detectLastMinuteGoal(allEvents, homeTeamId);
    const lastMinuteGoalWillRender = !!(lastMinuteGoal
        && ((lastMinuteGoal.wasWinner && t.summaryLastMinuteWinner)
            || (lastMinuteGoal.wasEqualizer && t.summaryLastMinuteEqualizer)));
    const shotEvents = allEvents.filter(e =>
        e.type === 'shot_on_target' || e.type === 'shot_off_target'
    );

    // Position-based upset/expected-win detection (league standings)
    const winnerPosition = winnerId === homeTeamId ? homePosition : awayPosition;
    const loserPosition = winnerId === homeTeamId ? awayPosition : homePosition;
    const positionGap = (winnerPosition && loserPosition) ? loserPosition - winnerPosition : 0;
    const isUpset = winnerId && positionGap < -6;
    const isExpectedDomination = winnerId && isBlowout && positionGap > 6;

    const replacements = {
        ':del_winner': winnerForms?.del || '',
        ':del_loser': loserForms?.del || '',
        ':del_home': homeForms.del,
        ':del_away': awayForms.del,
        ':al_winner': winnerForms?.al || '',
        ':al_loser': loserForms?.al || '',
        ':al_home': homeForms.al,
        ':al_away': awayForms.al,
        ':el_winner': winnerForms?.el || '',
        ':el_loser': loserForms?.el || '',
        ':el_home': homeForms.el,
        ':el_away': awayForms.el,
        ':winner': winnerForms?.name || '',
        ':loser': loserForms?.name || '',
        ':home': homeForms.name,
        ':away': awayForms.name,
        ':score_regular': regularScoreStr,
        ':score': scoreStr,
        ':pen_score': penaltyResult ? `${penaltyResult.home}-${penaltyResult.away}` : '',
        ':goals_each': String(totalHome),
        ':venue': venueName || '',
        ':en_venue': venueEnPhrase || '',
        ':el_venue': venueElPhrase || '',
        ':del_venue': venueDePhrase || '',
        ':competition': competitionName || '',
    };

    const conjunction = t.summaryScorerJoinAnd || 'and';

    const sentences = [];

    sentences.push(buildOpening(t, replacements, {
        isDraw, isGoalless, isBlowout, isNarrowWin,
        isKnockoutDecisive, isHighStakes, isChampion,
        hasExtraTime, penaltyResult,
        winnerId, homeTeamId,
    }));

    if (!isGoalless) {
        const goalNarrative = buildGoalNarrative(t, replacements, {
            allEvents, homeTeamId, isDraw, conjunction,
            homeForms, awayForms,
            lastMinuteGoal: lastMinuteGoalWillRender ? lastMinuteGoal : null,
        });
        if (goalNarrative) sentences.push(goalNarrative);
    }

    const keyMoment = buildKeyMoment(t, replacements, {
        allEvents, homeTeamId, winnerId,
        totalHome, totalAway, isDraw,
        homeForms, awayForms,
    });
    if (keyMoment) sentences.push(keyMoment);

    const color = buildColorComment(t, replacements, {
        homeTeamId, awayTeamId,
        totalGoals, isGoalless,
        hatTrick, lastMinuteGoal, shotEvents,
        isUpset, isExpectedDomination,
        homeForms, awayForms,
        winnerForms, loserForms,
    });
    if (color) sentences.push(color);

    if (!isCup) {
        const formComment = buildFormComment(t, {
            homeForm, awayForm,
            homeForms, awayForms,
            homeTeamId, awayTeamId,
            totalHome, totalAway,
        });
        if (formComment) sentences.push(formComment);
    }

    if (mvpPlayerName && t.summaryMvpClosing && Math.random() < 0.7) {
        const mvpTeamName = mvpPlayerTeamId === homeTeamId
            ? homeForms.name
            : (mvpPlayerTeamId === awayTeamId ? awayForms.name : '');
        sentences.push(pickTemplate(t.summaryMvpClosing, {
            ...replacements,
            ':player': mvpPlayerName,
            ':team': mvpTeamName,
        }));
    }

    const shout = pickTemplate(t.summaryShout || [], {
        ':venue': venueName || '',
        ':en_venue': venueEnPhrase || '',
        ':el_venue': venueElPhrase || '',
        ':del_venue': venueDePhrase || '',
    });
    if (shout) sentences.unshift(shout);

    return capitalizeSentences(sentences.filter(Boolean).join(' '));
}

function buildOpening(t, replacements, ctx) {
    const {
        isDraw, isGoalless, isBlowout, isNarrowWin,
        isKnockoutDecisive, isHighStakes, isChampion,
        hasExtraTime, penaltyResult,
        winnerId, homeTeamId,
    } = ctx;

    if (penaltyResult) {
        return pickTemplate(t.summaryOpeningPenalties, replacements);
    }

    if (hasExtraTime && winnerId) {
        return pickTemplate(t.summaryOpeningExtraTime, replacements);
    }

    if (isChampion && t.summaryOpeningHighStakesChampion) {
        return pickTemplate(t.summaryOpeningHighStakesChampion, replacements);
    }

    if (isHighStakes && winnerId && t.summaryOpeningHighStakesWin) {
        return pickTemplate(t.summaryOpeningHighStakesWin, replacements);
    }

    if (isKnockoutDecisive && winnerId && t.summaryOpeningCupWin) {
        return pickTemplate(t.summaryOpeningCupWin, replacements);
    }

    if (isKnockoutDecisive && isDraw && t.summaryOpeningCupDraw) {
        return pickTemplate(t.summaryOpeningCupDraw, replacements);
    }

    if (isGoalless) {
        return pickTemplate(t.summaryOpeningGoalless, replacements);
    }

    if (isDraw) {
        return pickTemplate(t.summaryOpeningDraw, replacements);
    }

    if (isBlowout) {
        return pickTemplate(t.summaryOpeningBlowout, replacements);
    }

    if (isNarrowWin) {
        return pickTemplate(t.summaryOpeningNarrowWin, replacements);
    }

    if (winnerId === homeTeamId) {
        return pickTemplate(t.summaryOpeningHomeWin, replacements);
    }
    return pickTemplate(t.summaryOpeningAwayWin, replacements);
}

function buildGoalNarrative(t, replacements, ctx) {
    const { allEvents, homeTeamId, isDraw, conjunction, homeForms, awayForms, lastMinuteGoal } = ctx;

    const goals = allEvents.filter(e => e.type === 'goal' || e.type === 'own_goal');
    if (goals.length === 0) return '';

    // Skip when the color-commentary step will already name the same scorer(s):
    // the only goal of the match is the last-minute winner, or one of the two
    // goals in a draw is the last-minute equalizer.
    if (lastMinuteGoal && (goals.length === 1 || (isDraw && goals.length === 2))) {
        return '';
    }

    // Own goals credit the opposing team. Tally on raw name so a player
    // who scored both a regular goal and a penalty counts as one entry.
    // Own goals are kept in a separate bucket entry so the "(own goal)"
    // annotation doesn't get swallowed by a regular goal in the same match.
    const homeTally = {};
    const awayTally = {};
    for (const g of goals) {
        const scoredForHome = (g.type === 'goal' && g.teamId === homeTeamId)
            || (g.type === 'own_goal' && g.teamId !== homeTeamId);
        const bucket = scoredForHome ? homeTally : awayTally;
        const rawName = g.playerName || '?';
        const isOwnGoal = g.type === 'own_goal';
        const key = isOwnGoal ? `${rawName}\0og` : rawName;
        if (!bucket[key]) {
            bucket[key] = { name: rawName, count: 0, isOwnGoal, hasPenalty: false };
        }
        bucket[key].count++;
        if (g.metadata?.is_penalty) {
            bucket[key].hasPenalty = true;
        }
    }

    const formatScorers = (tally) => {
        const names = Object.values(tally).map((entry) => {
            if (entry.count > 1) {
                return `${entry.name} (x${entry.count})`;
            }
            if (entry.isOwnGoal && t.summaryOwnGoalNote) {
                return `${entry.name} (${t.summaryOwnGoalNote})`;
            }
            if (entry.hasPenalty && t.summaryPenaltyGoalNote) {
                return `${entry.name} (${t.summaryPenaltyGoalNote})`;
            }
            return entry.name;
        });
        return joinScorers(names, conjunction);
    };

    const isSingleGoal = (tally) => {
        const entries = Object.values(tally);
        return entries.length === 1 && entries[0].count === 1;
    };

    const teamFragment = (tally, forms) => {
        const scorers = formatScorers(tally);
        const templates = isSingleGoal(tally)
            ? t.summaryGoalsTeamFragmentSingle
            : t.summaryGoalsTeamFragmentMulti;
        return pickTemplate(templates || [], {
            ...replacements,
            ...teamPlaceholders(forms),
            ':scorer': scorers,
            ':scorers': scorers,
        });
    };

    const homeScored = Object.keys(homeTally).length > 0;
    const awayScored = Object.keys(awayTally).length > 0;

    if (homeScored && awayScored) {
        return pickTemplate(t.summaryGoalsTwoTeamsJoin || [], {
            ':a': teamFragment(homeTally, homeForms),
            ':b': teamFragment(awayTally, awayForms),
        });
    }

    const onlyTally = homeScored ? homeTally : awayTally;
    const onlyForms = homeScored ? homeForms : awayForms;
    const scorers = formatScorers(onlyTally);
    const templates = isSingleGoal(onlyTally)
        ? t.summaryGoalsOneTeamSingleScorer
        : t.summaryGoalsOneTeam;
    return pickTemplate(templates || [], {
        ...replacements,
        ...teamPlaceholders(onlyForms),
        ':scorer': scorers,
        ':scorers': scorers,
    });
}

function buildKeyMoment(t, replacements, ctx) {
    const {
        allEvents, homeTeamId, winnerId,
        totalHome, totalAway, isDraw,
        homeForms, awayForms,
    } = ctx;

    // Comeback: team that conceded first wins.
    if (winnerId && !isDraw) {
        const goals = allEvents.filter(e => e.type === 'goal' || e.type === 'own_goal');
        if (goals.length >= 2) {
            const firstGoal = goals.reduce((earliest, e) =>
                e.minute < earliest.minute ? e : earliest
            , goals[0]);

            let firstScoringTeam;
            if (firstGoal.type === 'own_goal') {
                firstScoringTeam = firstGoal.teamId === homeTeamId ? awayForms : homeForms;
            } else {
                firstScoringTeam = firstGoal.teamId === homeTeamId ? homeForms : awayForms;
            }

            const winnerTeamForms = winnerId === homeTeamId ? homeForms : awayForms;
            if (firstScoringTeam.name !== winnerTeamForms.name && t.summaryComeback) {
                return pickTemplate(t.summaryComeback, replacements);
            }
        }
    }

    const redCards = allEvents.filter(e => e.type === 'red_card');
    if (redCards.length > 0) {
        const byTeam = {};
        for (const rc of redCards) {
            byTeam[rc.teamId] = byTeam[rc.teamId] || [];
            byTeam[rc.teamId].push(rc);
        }

        for (const [teamId, cards] of Object.entries(byTeam)) {
            if (cards.length >= 2 && t.summaryRedCardsMultiple) {
                const teamF = teamId === homeTeamId ? homeForms : awayForms;
                return pickTemplate(t.summaryRedCardsMultiple, {
                    ...replacements,
                    ...teamPlaceholders(teamF),
                    ':count': String(cards.length),
                });
            }
        }

        if (redCards.length === 1 && t.summaryRedCardSingle) {
            const rc = redCards[0];
            const rcTeam = rc.teamId === homeTeamId ? homeForms : awayForms;
            return pickTemplate(t.summaryRedCardSingle, {
                ...replacements,
                ...teamPlaceholders(rcTeam),
                ':player': rc.playerName || '?',
                ':minute': String(rc.minute),
            });
        }
    }

    if (totalHome + totalAway >= 2) {
        const goals = allEvents.filter(e => e.type === 'goal' || e.type === 'own_goal');
        const firstHalf = goals.filter(e => e.minute <= 45);
        const secondHalf = goals.filter(e => e.minute > 45);

        if (secondHalf.length === 0 && firstHalf.length >= 2 && t.summaryDominantFirstHalf) {
            return pickTemplate(t.summaryDominantFirstHalf, replacements);
        }
        if (firstHalf.length === 0 && secondHalf.length >= 2 && t.summaryDominantSecondHalf) {
            return pickTemplate(t.summaryDominantSecondHalf, replacements);
        }
    }

    return '';
}

/**
 * Color commentary: emotional, opinionated takes based on match drama.
 * Picks the single most interesting story to tell.
 */
function buildColorComment(t, replacements, ctx) {
    const {
        homeTeamId, awayTeamId,
        totalGoals, isGoalless,
        hatTrick, lastMinuteGoal, shotEvents,
        isUpset, isExpectedDomination,
        homeForms, awayForms,
        winnerForms, loserForms,
    } = ctx;

    if (lastMinuteGoal) {
        const ev = lastMinuteGoal.event;
        // Own goals benefit the opposing team; regular goals credit the scorer's team.
        const creditedTeamId = ev.type === 'own_goal'
            ? (ev.teamId === homeTeamId ? awayTeamId : homeTeamId)
            : ev.teamId;
        const lmTeam = creditedTeamId === homeTeamId ? homeForms : awayForms;
        const r = {
            ...replacements,
            ...teamPlaceholders(lmTeam),
            ':player': ev.playerName || '?',
            ':minute': String(ev.minute),
        };
        if (lastMinuteGoal.wasWinner && t.summaryLastMinuteWinner) {
            return pickTemplate(t.summaryLastMinuteWinner, r);
        }
        if (lastMinuteGoal.wasEqualizer && t.summaryLastMinuteEqualizer) {
            return pickTemplate(t.summaryLastMinuteEqualizer, r);
        }
    }

    if (hatTrick && t.summaryHatTrick) {
        const htTeam = hatTrick.teamId === homeTeamId ? homeForms : awayForms;
        return pickTemplate(t.summaryHatTrick, {
            ...replacements,
            ...teamPlaceholders(htTeam),
            ':player': hatTrick.playerName,
            ':goals': String(hatTrick.count),
        });
    }

    if (isUpset && t.summaryUpset) {
        return pickTemplate(t.summaryUpset, {
            ...replacements,
            ':el_winner': winnerForms?.el || '',
            ':del_winner': winnerForms?.del || '',
            ':al_loser': loserForms?.al || '',
            ':el_loser': loserForms?.el || '',
        });
    }

    if (isExpectedDomination && t.summaryExpectedWin) {
        return pickTemplate(t.summaryExpectedWin, {
            ...replacements,
            ':el_winner': winnerForms?.el || '',
            ':del_winner': winnerForms?.del || '',
            ':el_loser': loserForms?.el || '',
        });
    }

    if (totalGoals >= 5 && t.summaryHighScoring) {
        return pickTemplate(t.summaryHighScoring, {
            ...replacements,
            ':total': String(totalGoals),
        });
    }

    if (isGoalless && shotEvents.length <= 6 && t.summaryFewChances) {
        return pickTemplate(t.summaryFewChances, replacements);
    }

    return '';
}

function buildFormComment(t, ctx) {
    const {
        homeForm, awayForm,
        homeForms, awayForms,
        homeTeamId, awayTeamId,
        totalHome, totalAway,
    } = ctx;

    if (!homeForm?.length && !awayForm?.length) return '';

    const homeResult = totalHome > totalAway ? 'W' : (totalHome < totalAway ? 'L' : 'D');
    const awayResult = totalAway > totalHome ? 'W' : (totalAway < totalHome ? 'L' : 'D');

    const teams = [
        { form: [...(homeForm || []), homeResult], teamForms: homeForms, result: homeResult, id: homeTeamId },
        { form: [...(awayForm || []), awayResult], teamForms: awayForms, result: awayResult, id: awayTeamId },
    ];

    const pickForm = (templates, team, count) => pickTemplate(templates, {
        ...teamPlaceholders(team.teamForms),
        ':count': String(count),
    });

    for (const team of teams) {
        if (team.result === 'L') {
            const losses = countTrailingStreak(team.form, (r) => r === 'L');
            if (losses >= 3 && t.summaryFormLosingStreak) {
                return pickForm(t.summaryFormLosingStreak, team, losses);
            }
        }
    }

    for (const team of teams) {
        if (team.result === 'W') {
            const wins = countTrailingStreak(team.form, (r) => r === 'W');
            if (wins >= 3 && t.summaryFormWinningStreak) {
                return pickForm(t.summaryFormWinningStreak, team, wins);
            }
        }
    }

    for (const team of teams) {
        if (team.result !== 'W') {
            const winless = countTrailingStreak(team.form, (r) => r !== 'W');
            if (winless >= 3 && t.summaryFormWinless) {
                return pickForm(t.summaryFormWinless, team, winless);
            }
        }
    }

    return '';
}
