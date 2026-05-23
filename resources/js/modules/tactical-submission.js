/**
 * Tactical-actions POST pipeline: applies user substitutions and/or
 * tactics server-side, merges the resimulated events back into the
 * canonical `realEvents` list, and refreshes scores/possession/ratings.
 * Atmosphere events are then re-derived in one shot from the updated
 * real-event list — there is no longer a "regenerate-then-merge"
 * coordination dance that earlier versions of this module had to do by
 * hand.
 *
 * Isolated from the UI panel (tactical-panel.js) so the network + state-
 * reconciliation flow can be reasoned about on its own.
 */
import { MINUTE, FREE_SUB_WINDOW_MINUTES, effectiveSubmissionMinute, isHalfTimeLike } from './match-phases.js';
import { updateRosterPerformances } from './player-ratings.js';

function recomputeLastRevealedIndex(events, currentMinute) {
    let idx = -1;
    for (let i = 0; i < events.length; i++) {
        if (events[i].minute <= currentMinute) {
            idx = i;
        } else {
            break;
        }
    }
    return idx;
}

export function createTacticalSubmission(ctx) {
    return {
        async confirmAllChanges() {
            const c = ctx();

            // Auto-add selected pair to pending if present
            if (c.selectedPlayerOut && c.selectedPlayerIn) {
                c.addPendingSub();
            }

            if (c.applyingChanges) return;

            if (!c.hasPendingChanges) {
                if (c.showingConfirmation) {
                    c.tacticalError = c.translations.tacticalErrorNoPending
                        || 'No changes to apply.';
                    c.showingConfirmation = false;
                }
                return;
            }

            c.tacticalError = null;
            c.applyingChanges = true;

            // At half-time, c.currentMinute has been snapped back to 45 by
            // enterHalfTime, but events revealed during 1H stoppage have
            // absolute minutes > 45. Using floor(currentMinute) directly
            // would cause the backend revert and the local event filters
            // below to strip those events.
            const minute = effectiveSubmissionMinute(c);
            const isHalfTime = isHalfTimeLike(c.phase);

            try {
                const payload = {
                    minute,
                    is_half_time: isHalfTime,
                    previousSubstitutions: c.substitutionsMade.map(s => ({
                        playerOutId: s.playerOutId,
                        playerInId: s.playerInId,
                        minute: s.minute,
                    })),
                };

                // Include subs if any
                if (c.pendingSubs.length > 0) {
                    payload.substitutions = c.pendingSubs.map(s => ({
                        playerOutId: s.playerOut.id,
                        playerInId: s.playerIn.id,
                    }));
                }

                // Include tactical changes if any
                if (c.hasTacticalChanges) {
                    if (c.pendingFormation !== null && c.pendingFormation !== c.activeFormation) {
                        payload.formation = c.pendingFormation;
                    }
                    if (c.pendingMentality !== null && c.pendingMentality !== c.activeMentality) {
                        payload.mentality = c.pendingMentality;
                    }
                    if (c.pendingPlayingStyle !== null && c.pendingPlayingStyle !== c.activePlayingStyle) {
                        payload.playing_style = c.pendingPlayingStyle;
                    }
                    if (c.pendingPressing !== null && c.pendingPressing !== c.activePressing) {
                        payload.pressing = c.pendingPressing;
                    }
                    if (c.pendingDefLine !== null && c.pendingDefLine !== c.activeDefLine) {
                        payload.defensive_line = c.pendingDefLine;
                    }
                    // In-match drag swaps → server-side manual pins so
                    // the user's explicit slot intent survives the
                    // post-sub reshuffle.
                    if (Object.keys(c._manualSlotPins).length > 0) {
                        payload.manual_slot_pins = { ...c._manualSlotPins };
                    }
                }

                const response = await fetch(c.tacticalActionsUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': c.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                if (!response.ok) {
                    let errorMessage = c.translations.tacticalErrorGeneric
                        || 'Something went wrong. Please try again.';
                    try {
                        const errorData = await response.json();
                        console.error('Tactical actions failed:', errorData);
                        if (errorData.error) {
                            errorMessage = errorData.error;
                        }
                    } catch (parseErr) {
                        console.error('Tactical actions failed (non-JSON response):', response.status);
                    }
                    c.tacticalError = errorMessage;
                    c.applyingChanges = false;
                    return;
                }

                const result = await response.json();
                const isET = result.isExtraTime || false;

                // Record substitutions if any
                if (result.substitutions && result.substitutions.length > 0) {
                    for (const sub of result.substitutions) {
                        c.substitutionsMade.push({
                            playerOutId: sub.playerOutId,
                            playerInId: sub.playerInId,
                            playerOutName: sub.playerOutName,
                            playerInName: sub.playerInName,
                            minute,
                        });

                        const benchPlayer = c.benchPlayers.find(p => p.id === sub.playerInId);
                        if (benchPlayer) {
                            benchPlayer.minuteEntered = minute;
                        }
                    }
                }

                // Update active tactics
                if (result.formation) {
                    // Custom pitch positions are keyed by slot IDs whose
                    // grid-cell meaning is formation-specific, so a formation
                    // change makes the saved positions meaningless against the
                    // new shape. Drop them in lockstep with the formation
                    // bump — mirrors what the lineup page does in
                    // updateAutoLineup() — and keep _pitchPositionsFormation
                    // pointing at the formation the (now-empty) map belongs to.
                    if (result.formation !== c.activeFormation) {
                        c.livePitchPositions = {};
                    }
                    c.activeFormation = result.formation;
                    c._pitchPositionsFormation = result.formation;
                }
                // Promote the authoritative post-apply slot map returned by
                // the server. TacticalChangeService recomputes it on every
                // tactical action (subs, formation change, red-card
                // reshuffle), so we replace startingSlotMap unconditionally.
                // Drag-swap intent is consumed: the server's reshuffle is
                // the new baseline, and any future manual swaps start fresh.
                if (result.slot_assignments) {
                    c.startingSlotMap = result.slot_assignments;
                    c._manualSlotPins = {};
                }
                // Pending-state reset includes the preview map.
                c.previewSlotMap = null;
                if (result.mentality) c.activeMentality = result.mentality;
                if (result.playingStyle) c.activePlayingStyle = result.playingStyle;
                if (result.pressing) c.activePressing = result.pressing;
                if (result.defensiveLine) c.activeDefLine = result.defensiveLine;

                // Drop the resimulated portion of realEvents and stale
                // atmosphere — both will be reseeded from the server's
                // newEvents + the regenerator below. The revealed feed
                // keeps everything ≤ minute except contextual narratives
                // (which reflected the pre-resimulation score and need to
                // be redrawn from the new score timeline).
                if (isET) {
                    c.realExtraTimeEvents = c.realExtraTimeEvents.filter(e => e.minute <= minute);
                } else {
                    c.realEvents = c.realEvents.filter(e => e.minute <= minute);
                }
                c.revealedEvents = c.revealedEvents.filter(e => e.minute <= minute && e.type !== 'contextual');

                if (result.substitutions) {
                    for (const sub of result.substitutions) {
                        const subRevealEvent = {
                            minute,
                            type: 'substitution',
                            playerName: sub.playerOutName,
                            playerInName: sub.playerInName,
                            teamId: sub.teamId,
                            displayMinute: sub.displayMinute,
                            phase: sub.phase,
                        };
                        // At half-time, unshift would put the sub at the top
                        // of revealedEvents — above the 1H-stoppage atmosphere
                        // events still classified into the 2H bucket — so it
                        // would render furthest from the half-time divider.
                        // Push appends to the chronologically-oldest end of
                        // the reverse-chronological feed, so the sub renders
                        // immediately above the DESCANSO line. During regular
                        // play it stays unshift = newest-on-top.
                        if (isHalfTime) {
                            c.revealedEvents.push(subRevealEvent);
                        } else {
                            c.revealedEvents.unshift(subRevealEvent);
                        }
                    }

                    // Client-injected substitution events ARE real events:
                    // they change who is on the pitch, and atmosphere must
                    // honor them when picking shot actors.
                    const subEvents = result.substitutions.map(sub => ({
                        minute,
                        type: 'substitution',
                        playerName: sub.playerOutName,
                        playerInName: sub.playerInName,
                        teamId: sub.teamId,
                        gamePlayerId: sub.playerOutId,
                        metadata: { player_in_id: sub.playerInId },
                        displayMinute: sub.displayMinute,
                        phase: sub.phase,
                    }));

                    if (isET) {
                        c.realExtraTimeEvents.push(...subEvents);
                    } else {
                        c.realEvents.push(...subEvents);
                    }
                }

                // Merge the server's resimulated events into the canonical
                // real-event list. Atmosphere is regenerated from this
                // list below, so any red card / injury / sub in
                // result.newEvents is visible to the shot generator.
                if (isET) {
                    if (result.newEvents && result.newEvents.length > 0) {
                        c.realExtraTimeEvents.push(...result.newEvents);
                    }
                    c.realExtraTimeEvents.sort((a, b) => a.minute - b.minute);

                    c.etHomeScore = result.newScore.home;
                    c.etAwayScore = result.newScore.away;
                    c._needsPenalties = result.needsPenalties || false;

                    c.recomputeETAtmosphere();

                    c.lastRevealedETIndex = recomputeLastRevealedIndex(c.extraTimeEvents, c.currentMinute);
                } else {
                    if (result.newEvents && result.newEvents.length > 0) {
                        c.realEvents.push(...result.newEvents);
                    }
                    c.realEvents.sort((a, b) => a.minute - b.minute);

                    c.finalHomeScore = result.newScore.home;
                    c.finalAwayScore = result.newScore.away;

                    // Synthesize ghost goals into the canonical list so the
                    // displayed score matches even when the server omitted
                    // goal events. Recomputed atmosphere then sees them.
                    c.realEvents = c.synthesizeGoalsIfNeeded(c.realEvents);

                    c.recomputeRegularAtmosphere();

                    // Compare against `minute` (the effective submission
                    // minute) rather than c.currentMinute. At half-time
                    // enterHalfTime snapped currentMinute back to 45, but
                    // the user has already watched events up through
                    // 45+fhs. Using currentMinute here would leave the
                    // 1H-stoppage events (and the just-pushed sub event
                    // at minute=45+fhs) past the index, so the next 2H
                    // tick would re-reveal them — duplicating them in
                    // revealedEvents.
                    c.lastRevealedIndex = recomputeLastRevealedIndex(c.events, minute);
                }

                c.recalculateScore();

                // Update possession
                if (result.homePossession !== undefined) {
                    c._basePossession = result.homePossession;
                    c._possessionDisplay = result.homePossession;
                    c.homePossession = result.homePossession;
                    c.awayPossession = result.awayPossession;
                    c.resetPossessionTarget();
                }

                // Update player performances and recalculate ratings
                if (result.playerPerformances) {
                    updateRosterPerformances(
                        [c.homeLineupRoster, c.awayLineupRoster, c.benchPlayers, c.opponentBenchPlayers],
                        result.playerPerformances,
                    );
                    c.recalculatePlayerRatings();
                }

                // Update MVP after resimulation
                if (result.mvpPlayerName !== undefined) {
                    c.mvpPlayerName = result.mvpPlayerName;
                    c.mvpPlayerTeamId = result.mvpPlayerTeamId;
                }

                // Close the panel and resume
                c.closeTacticalPanel();
            } catch (err) {
                console.error('Tactical actions request failed:', err);
                c.tacticalError = c.translations.tacticalErrorGeneric
                    || 'Something went wrong. Please try again.';
            } finally {
                c.applyingChanges = false;
            }
        },

        /**
         * Called by skipToEnd() before the client-only fast-forward.
         * Asks the backend to re-simulate the remainder of the regular-time
         * match with AI substitutions enabled for the user's team — so
         * players who fast-forward don't finish with the tired starting 11.
         *
         * Returns true if the backend produced new events (caller should
         * use the updated state.events), false if the call was skipped,
         * no-op'd, or failed.
         */
        async autoSubUserTeamBeforeSkip(minute) {
            const c = ctx();

            // Guard: endpoint, phase, bench, sub budget.
            if (!c.skipToEndUrl) return false;
            if (minute >= MINUTE.REGULAR_TIME_END) return false;
            if (!c.benchPlayers || c.benchPlayers.length === 0) return false;
            if (c.substitutionsMade.length >= c.maxSubstitutions) return false;

            // Windows-used check (mirrors getWindowsUsed in substitution-manager).
            const freeMinutes = c.freeSubWindowMinutes || FREE_SUB_WINDOW_MINUTES;
            const usedWindowMinutes = new Set(c.substitutionsMade.map(s => s.minute));
            freeMinutes.forEach(m => usedWindowMinutes.delete(m));
            if (usedWindowMinutes.size >= c.maxWindows) return false;

            let result;
            try {
                const response = await fetch(c.skipToEndUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': c.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        minute,
                        previousSubstitutions: c.substitutionsMade.map(s => ({
                            playerOutId: s.playerOutId,
                            playerInId: s.playerInId,
                            minute: s.minute,
                        })),
                    }),
                });

                if (!response.ok) {
                    console.warn('Skip-to-end auto-sub request returned', response.status);
                    return false;
                }

                result = await response.json();
            } catch (err) {
                console.warn('Skip-to-end auto-sub request failed, falling back:', err);
                return false;
            }

            if (!result || !result.autoSubsApplied) {
                return false;
            }

            // Merge: drop pre-computed events after the skip minute and
            // replace them with the freshly-simulated remainder.
            c.realEvents = c.realEvents.filter(e => e.minute <= minute);

            // Merge server-resimulated events into realEvents and update
            // scores BEFORE regenerating atmosphere — atmosphere is derived
            // from the up-to-date realEvents in a single pass.
            if (result.newEvents && result.newEvents.length > 0) {
                c.realEvents.push(...result.newEvents);
            }
            c.realEvents.sort((a, b) => a.minute - b.minute);

            if (result.newScore) {
                c.finalHomeScore = result.newScore.home;
                c.finalAwayScore = result.newScore.away;
            }

            c.recomputeRegularAtmosphere();

            // Reset the revealed-events feed and substitution tracking,
            // then re-reveal ALL events in one synchronous pass.
            c.revealedEvents = [];
            c.substitutionsMade = [];
            c.lastRevealedIndex = -1;
            for (let i = 0; i < c.events.length; i++) {
                const event = c.events[i];
                c.revealedEvents.unshift(event);
                c.lastRevealedIndex = i;
                if (event.type === 'substitution' && event.teamId === c.userTeamId) {
                    c.substitutionsMade.push({
                        playerOutId: event.gamePlayerId,
                        playerInId: event.metadata?.player_in_id ?? '',
                        minute: event.minute,
                        playerOutName: event.playerName ?? '',
                        playerInName: event.playerInName ?? '',
                    });
                }
            }
            c.homeScore = c.finalHomeScore;
            c.awayScore = c.finalAwayScore;

            // Update possession bar.
            if (result.homePossession !== undefined) {
                c._basePossession = result.homePossession;
                c._possessionDisplay = result.homePossession;
                c.homePossession = result.homePossession;
                c.awayPossession = result.awayPossession;
                if (typeof c.resetPossessionTarget === 'function') {
                    c.resetPossessionTarget();
                }
            }

            // Update player performances and post-match ratings.
            if (result.playerPerformances) {
                updateRosterPerformances(
                    [c.homeLineupRoster, c.awayLineupRoster, c.benchPlayers, c.opponentBenchPlayers],
                    result.playerPerformances,
                );
                if (typeof c.recalculatePlayerRatings === 'function') {
                    c.recalculatePlayerRatings();
                }
            }

            // Update MVP (may have changed after resimulation).
            if (result.mvpPlayerName !== undefined) {
                c.mvpPlayerName = result.mvpPlayerName;
                c.mvpPlayerTeamId = result.mvpPlayerTeamId;
            }

            return true;
        },
    };
}
