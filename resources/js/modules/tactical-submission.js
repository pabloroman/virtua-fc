/**
 * Tactical-actions POST pipeline: applies user substitutions and/or
 * tactics server-side, merges the resimulated events back into the feed,
 * and refreshes scores/possession/ratings.
 *
 * Isolated from the UI panel (tactical-panel.js) so the network + state-
 * reconciliation flow can be reasoned about on its own.
 */
import { MINUTE, FREE_SUB_WINDOW_MINUTES, resolveMinuteForTacticalChange } from './match-phases.js';
import { regenerateShots, regenerateNarratives } from './atmosphere-generator.js';
import { updateRosterPerformances } from './player-ratings.js';

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

            const minute = resolveMinuteForTacticalChange(c.currentMinute, c.phase);

            try {
                const payload = {
                    minute,
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

                // Filter server events up to current minute. Atmosphere events
                // (shots/fouls) beyond this minute are discarded and regenerated
                // below so they reflect substitutions and tactical changes.
                if (isET) {
                    c.extraTimeEvents = c.extraTimeEvents.filter(e => e.minute <= minute);
                } else {
                    c.events = c.events.filter(e => e.minute <= minute);
                    // Remove contextual narratives — they'll be freshly regenerated
                    // below to reflect the post-resimulation score.
                    c.events = c.events.filter(e => e.type !== 'contextual');
                }
                c.revealedEvents = c.revealedEvents.filter(e => e.minute <= minute && e.type !== 'contextual');

                if (result.substitutions) {
                    for (const sub of result.substitutions) {
                        c.revealedEvents.unshift({
                            minute,
                            type: 'substitution',
                            playerName: sub.playerOutName,
                            playerInName: sub.playerInName,
                            teamId: sub.teamId,
                        });
                    }

                    // Also add substitution events to the main events array so
                    // the atmosphere generator can track who is on/off the pitch.
                    const subEvents = result.substitutions.map(sub => ({
                        minute,
                        type: 'substitution',
                        playerName: sub.playerOutName,
                        playerInName: sub.playerInName,
                        teamId: sub.teamId,
                        gamePlayerId: sub.playerOutId,
                        metadata: { player_in_id: sub.playerInId },
                    }));

                    if (isET) {
                        c.extraTimeEvents.push(...subEvents);
                    } else {
                        c.events.push(...subEvents);
                    }
                }

                // Regenerate atmosphere shot events for the remaining match
                // period, now aware of substitutions.
                const atmCfg = c._atmosphereConfig();
                regenerateShots({
                    config: atmCfg,
                    target: isET ? c.extraTimeEvents : c.events,
                    availabilityEvents: isET ? [...c.events, ...c.extraTimeEvents] : c.events,
                    minMinute: minute + 1,
                    maxMinute: isET ? MINUTE.ET_END : MINUTE.REGULAR_TIME_END,
                });

                // Append new events and update scores
                if (isET) {
                    if (result.newEvents && result.newEvents.length > 0) {
                        c.extraTimeEvents.push(...result.newEvents);
                        c.extraTimeEvents.sort((a, b) => a.minute - b.minute);
                    }

                    regenerateNarratives({
                        config: c._atmosphereConfig(),
                        target: c.extraTimeEvents,
                        availabilityEvents: [...c.events, ...c.extraTimeEvents],
                        minMinute: minute + 1,
                    });

                    c.lastRevealedETIndex = -1;
                    for (let i = 0; i < c.extraTimeEvents.length; i++) {
                        if (c.extraTimeEvents[i].minute <= c.currentMinute) {
                            c.lastRevealedETIndex = i;
                        } else {
                            break;
                        }
                    }

                    c.etHomeScore = result.newScore.home;
                    c.etAwayScore = result.newScore.away;
                    c._needsPenalties = result.needsPenalties || false;
                } else {
                    if (result.newEvents && result.newEvents.length > 0) {
                        c.events.push(...result.newEvents);
                        c.events.sort((a, b) => a.minute - b.minute);
                    }

                    c.finalHomeScore = result.newScore.home;
                    c.finalAwayScore = result.newScore.away;

                    c.events = c.synthesizeGoalsIfNeeded(c.events);

                    // Regenerate narratives: goal text for new server goals + contextual
                    // commentary for checkpoints after the tactical minute (old ones were
                    // removed because they reflected the pre-resimulation score).
                    regenerateNarratives({
                        config: c._atmosphereConfig(),
                        target: c.events,
                        availabilityEvents: c.events,
                        minMinute: minute + 1,
                        includeContextual: true,
                    });

                    // Recalculate after all event modifications (synthesize, narratives)
                    // to avoid stale indices from array insertions and re-sorts.
                    c.lastRevealedIndex = -1;
                    for (let i = 0; i < c.events.length; i++) {
                        if (c.events[i].minute <= c.currentMinute) {
                            c.lastRevealedIndex = i;
                        } else {
                            break;
                        }
                    }
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
            c.events = c.events.filter(e => e.minute <= minute);

            // Regenerate shots for the skipped-over window BEFORE merging the
            // server's resimulated events, matching the tactical-change ordering.
            const atmCfg = c._atmosphereConfig();
            regenerateShots({
                config: atmCfg,
                target: c.events,
                availabilityEvents: c.events,
                minMinute: minute + 1,
                maxMinute: MINUTE.REGULAR_TIME_END,
            });

            if (result.newEvents && result.newEvents.length > 0) {
                c.events.push(...result.newEvents);
                c.events.sort((a, b) => a.minute - b.minute);
            }

            // Update final score (the resimulation may have changed it).
            if (result.newScore) {
                c.finalHomeScore = result.newScore.home;
                c.finalAwayScore = result.newScore.away;
            }

            // Regenerate narratives AFTER the newEvents merge so goal narratives
            // attach to the fresh server goals, plus contextual + tactical
            // commentary for post-skip checkpoints.
            regenerateNarratives({
                config: atmCfg,
                target: c.events,
                availabilityEvents: c.events,
                minMinute: minute + 1,
                includeContextual: true,
                includeTactical: true,
            });

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
