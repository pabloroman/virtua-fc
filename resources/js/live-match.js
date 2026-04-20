import {
    getEffectivePosition as _getEffectivePosition,
    getInitials as _getInitials,
    getShirtStyle as _getShirtStyle,
    getNumberStyle as _getNumberStyle,
    getPlayerEnergy as _getPlayerEnergy,
    getEnergyColor as _getEnergyColor,
    getEnergyBarBg as _getEnergyBarBg,
    getEnergyTextColor as _getEnergyTextColor,
    calculateDrainRate as _calculateDrainRate,
    getOvrBadgeClasses as _getOvrBadgeClasses,
    getPositionBadgeColor as _getPositionBadgeColor,
    getSecondaryBadgeClasses as _getSecondaryBadgeClasses,
    getSecondaryAbbr as _getSecondaryAbbr,
    isValidGridCell as _isValidGridCell,
    getZoneColorClass as _getZoneColorClass,
} from './modules/pitch-renderer.js';
import { createPitchGrid } from './modules/pitch-grid.js';
import { swapSlots } from './modules/slot-map.js';
import { createPenaltyShootout } from './modules/penalty-shootout.js';
import { createSubstitutionManager } from './modules/substitution-manager.js';
import { createMatchSimulation } from './modules/match-simulation.js';
import { createMatchStats } from './modules/match-stats.js';
import { createAtmosphereGlue } from './modules/atmosphere-glue.js';
import { createTacticalPanel } from './modules/tactical-panel.js';
import { createTacticalSubmission } from './modules/tactical-submission.js';
import { createPitchLayout } from './modules/pitch-layout.js';
import { mixinModule } from './modules/_mixin.js';
import {
    PHASE,
    MINUTE,
    isExtraTimePhase,
    isPlayingPhase,
    resolveMinuteForTacticalChange,
} from './modules/match-phases.js';
import { createKnockoutOutcome } from './modules/knockout-outcome.js';
import { createRatingsGlue } from './modules/ratings-glue.js';
import { createEventFeed } from './modules/event-feed.js';
import { createEventCache } from './modules/event-cache.js';

// Re-export for any consumers that imported it from this module.
export { resolveMinuteForTacticalChange };

export default function liveMatch(config) {
    // Create modules early with a deferred context reference so their
    // getters are defined on the raw data object BEFORE Alpine wraps it.
    // This ensures Alpine's reactivity system sees them as native getters.
    let _self = null;
    const ctx = () => _self;
    const subs = createSubstitutionManager(ctx);
    const penalties = createPenaltyShootout(ctx);
    const sim = createMatchSimulation(ctx);
    const stats = createMatchStats(ctx);
    const knockoutOutcome = createKnockoutOutcome(ctx);
    const ratings = createRatingsGlue(ctx);
    const eventFeed = createEventFeed(ctx);
    const eventCache = createEventCache({ matchId: config.matchId || '' });
    const atmosphere = createAtmosphereGlue(ctx);
    const tacticalPanel = createTacticalPanel(ctx);
    const tacticalSubmission = createTacticalSubmission(ctx);
    const pitchLayout = createPitchLayout(ctx);

    const component = {
        // Config (from server)
        events: config.events || [],
        homeTeamId: config.homeTeamId,
        awayTeamId: config.awayTeamId,
        finalHomeScore: config.finalHomeScore,
        finalAwayScore: config.finalAwayScore,
        otherMatches: config.otherMatches || [],
        homeTeamName: config.homeTeamName,
        awayTeamName: config.awayTeamName,
        homeTeamImage: config.homeTeamImage,
        awayTeamImage: config.awayTeamImage,
        userTeamId: config.userTeamId,

        // Substitution config
        lineupPlayers: config.lineupPlayers || [],
        benchPlayers: config.benchPlayers || [],
        // Opponent bench — minimal payload { id, positionGroup, performance, teamId }
        // used only to rate opponent subs at full-time. Not displayed in the UI.
        opponentBenchPlayers: config.opponentBenchPlayers || [],
        tacticalActionsUrl: config.tacticalActionsUrl || '',
        skipToEndUrl: config.skipToEndUrl || '',
        csrfToken: config.csrfToken || '',
        maxSubstitutions: config.maxSubstitutions || 5,
        maxWindows: config.maxWindows || 3,

        // Possession (base = server value, display = oscillating value shown in UI)
        _basePossession: config.homePossession || 50,
        homePossession: config.homePossession || 50,
        awayPossession: config.awayPossession || 50,
        _possessionTarget: config.homePossession || 50,
        _possessionNextShift: 2 + Math.random() * 2,
        _possessionDisplay: config.homePossession || 50,

        // Tactical config
        activeFormation: config.activeFormation || '4-3-3',
        activeMentality: config.activeMentality || 'balanced',
        activePlayingStyle: config.activePlayingStyle || 'balanced',
        activePressing: config.activePressing || 'standard',
        activeDefLine: config.activeDefLine || 'normal',
        opponentPlayingStyle: config.opponentPlayingStyle || 'balanced',
        opponentPressing: config.opponentPressing || 'standard',
        opponentDefLine: config.opponentDefLine || 'normal',
        opponentMentality: config.opponentMentality || 'balanced',
        availableFormations: config.availableFormations || [],
        availableMentalities: config.availableMentalities || [],
        availablePlayingStyles: config.availablePlayingStyles || [],
        availablePressing: config.availablePressing || [],
        availableDefLine: config.availableDefLine || [],
        translations: config.translations || {},

        // Extra time / knockout config
        isKnockout: config.isKnockout || false,
        isTwoLeggedTie: config.isTwoLeggedTie || false,
        extraTimeUrl: config.extraTimeUrl || '',
        penaltiesUrl: config.penaltiesUrl || '',
        twoLeggedInfo: config.twoLeggedInfo || null,
        preloadedExtraTimeData: config.extraTimeData || null,

        // Tournament knockout config
        isTournamentKnockout: config.isTournamentKnockout || false,
        knockoutRoundNumber: config.knockoutRoundNumber || null,
        knockoutRoundName: config.knockoutRoundName || '',

        // Animation state (server-side flag to skip animation on page refresh)
        matchId: config.matchId || '',
        animationSeen: config.animationSeen || false,

        // MVP
        mvpPlayerName: config.mvpPlayerName || null,
        mvpPlayerTeamId: config.mvpPlayerTeamId || null,

        // Player match ratings (computed client-side from performance data)
        playerRatings: {},
        hasSeenRatings: false,

        // Atmosphere generation (client-side commentary)
        homeLineupRoster: config.homeLineupRoster || [],
        awayLineupRoster: config.awayLineupRoster || [],
        venueName: config.venueName || '',
        venueEnPhrase: config.venueEnPhrase || '',
        venueElPhrase: config.venueElPhrase || '',
        venueDePhrase: config.venueDePhrase || '',
        homeArticle: config.homeArticle !== undefined ? config.homeArticle : 'el',
        awayArticle: config.awayArticle !== undefined ? config.awayArticle : 'el',
        narrativeTemplates: config.narrativeTemplates || {},

        // Match summary (generated at full time)
        matchSummary: null,
        homeForm: config.homeForm || [],
        awayForm: config.awayForm || [],
        homePosition: config.homePosition || null,
        awayPosition: config.awayPosition || null,
        competitionRole: config.competitionRole || 'league',
        competitionName: config.competitionName || '',

        // Pitch visualization config
        formationSlots: config.formationSlots || {},
        teamColors: config.teamColors || null,
        slotCompatibility: config.slotCompatibility || {},
        gridConfig: config.gridConfig || null,

        // Pitch interaction state (for tap-to-substitute)
        livePitchSelectedOutId: null,

        // Pitch drag-and-drop state (for repositioning players)
        draggingSlotId: null,
        dragPosition: null,
        positioningSlotId: null,
        livePitchPositions: config.pitchPositions || {},
        _pitchPositionsFormation: config.activeFormation || '4-3-3',
        // Authoritative slot map for the starting XI, seeded from the server.
        // Confirmed substitutions are replayed on top of this in the
        // slotAssignments getter, so we never mutate it directly except
        // when the formation changes mid-match.
        startingSlotMap: config.slotAssignments || {},

        // Slot map for the currently-previewed (but not yet committed)
        // formation. Populated by `refreshFormationPreview()` when the user
        // clicks a formation button in the tactical panel. Null means
        // either "not previewing" or "fetch in flight" — the getter falls
        // back to a local placement in that window so the pitch isn't empty.
        previewSlotMap: null,
        computeSlotsUrl: config.computeSlotsUrl || '',
        _previewFetchId: 0,
        // Manual slot pins recorded by in-match drag swaps, keyed by slot id
        // and storing the *effective* player id the user placed there (i.e.
        // what was visible on the pitch at drag time — not the stale
        // `startingSlotMap` entry, which may still hold a pending-out player).
        // These are preserved when the best-fit placement reshuffles around
        // pending subs, so the user's manual intent isn't silently undone,
        // and are forwarded to the server on Apply as manual_slot_pins.
        _manualSlotPins: {},

        // Formation-picker prompt state. When a staged substitution leaves a
        // player out of position in the current formation, showConfirmation()
        // opens this prompt instead of the normal confirm overlay. The user
        // either picks a better-fitting formation or explicitly accepts the
        // out-of-position penalty.
        formationPickerOpen: false,
        formationPickerOffenders: [],
        formationPickerSuggested: null,
        formationPickerLoading: false,

        // Tactical change state
        pendingFormation: null,
        pendingMentality: null,
        pendingPlayingStyle: null,
        pendingPressing: null,
        pendingDefLine: null,
        applyingChanges: false,
        showingConfirmation: false,
        tacticalError: null,

        // Tab state
        activeTab: 'events',
        showCommentary: localStorage.getItem('liveMatchCommentary') !== 'false',

        // Clock state
        currentMinute: 0,
        speed: [1, 2, 4].includes(parseFloat(localStorage.getItem('liveMatchSpeed')))
            ? parseFloat(localStorage.getItem('liveMatchSpeed'))
            : 2,
        // Phases: pre_match, first_half, half_time, second_half,
        //         going_to_extra_time, extra_time_first_half, extra_time_half_time,
        //         extra_time_second_half, penalties, full_time
        phase: 'pre_match',
        isPaused: false,
        userPaused: false,
        pauseTimer: null,

        // Derived state
        revealedEvents: [],
        homeScore: 0,
        awayScore: 0,
        lastRevealedIndex: -1,
        goalFlash: false,
        latestEvent: null,

        // Extra time state
        extraTimeEvents: [],
        extraTimeLoading: false,
        etHomeScore: 0,
        etAwayScore: 0,
        penaltyResult: null,
        lastRevealedETIndex: -1,
        _skipToEndFired: false,
        _skippingToEnd: false,
        _needsPenalties: false,

        // Penalty picker / shootout state
        penaltyPickerOpen: false,
        selectedPenaltyKickers: [],
        penaltyProcessing: false,
        penaltyKicks: [],           // All kick results from server
        revealedPenaltyKicks: [],   // Kicks revealed so far (animated)
        penaltyPreparing: false,    // Shows "preparing to shoot" state
        nextPenaltyKicker: null,    // Next kicker about to shoot

        // Tactical panel state
        tacticalPanelOpen: false,
        tacticalTab: 'substitutions',
        injuryAlertPlayer: null, // Name of the player that triggered auto-open

        // Substitution state
        selectedPlayerOut: null,
        selectedPlayerIn: null,
        pendingSubs: [],        // Queued subs for the current window [{playerOut, playerIn}]
        substitutionsMade: [],

        // Ticker state for other matches
        otherMatchScores: [],

        // Career actions processing state
        processingReady: false,
        processingStatusUrl: config.processingStatusUrl || '',
        _processingPollTimer: null,

        // Animation loop timers — managed internally by match-simulation module

        // Speed presets: match minutes per real second
        speedRates: {
            1: 1.5,   // ~60s for full match
            2: 3.0,   // ~30s for full match
            4: 6.0,   // ~15s for full match
        },

        init() {
            // Bind the deferred context so all module functions can access
            // the Alpine component instance from this point forward.
            _self = this;

            // Integrate shared pitch grid module (positioning + drag-and-drop).
            // Created here because its options reference `this` for callbacks.
            const grid = createPitchGrid(() => this, {
                allowGkDrag: false,
                allowGkReposition: false,
                allowGkSwap: false,
                dragThreshold: 5,
                onTapFallback: (slot) => this.handlePitchPlayerClick(slot),
                onPositionChanged: null,
                // Live match: drag = player-to-player swap within the
                // current formation. Grid-cell repositioning was removed
                // because it created an inconsistent mental model where
                // the pitch and the simulated role diverged (e.g. dragging
                // a striker to the defence "looked" right but kept the CB
                // slot compat penalty behind the scenes). The formation is
                // now the single source of truth for each player's role.
                onSwap: (draggedSlot, occupyingSlot) => {
                    // Pin the *displayed* occupants, not `startingSlotMap`
                    // entries. When a pending sub is staged the pitch shows
                    // a bestFit placement that may already include incoming
                    // bench players; swapping via startingSlotMap here would
                    // record pins for the outgoing players instead, and the
                    // bestFit rebuild would scatter the incoming player away
                    // from the dragged slot.
                    //
                    // `draggedSlot` comes from pitch-grid's `currentSlots`
                    // (raw formation slot definitions, no `.player`), so
                    // look the player up in `slotAssignments` which carries
                    // the rendered occupant. `occupyingSlot` already comes
                    // from `slotAssignments` via `_findSlotAtCell`.
                    const assignments = this.slotAssignments;
                    const draggedPlayerId = assignments.find(a => a.id === draggedSlot.id)?.player?.id;
                    const occupyingPlayerId = occupyingSlot.player?.id;
                    if (!draggedPlayerId || !occupyingPlayerId) return;

                    this._manualSlotPins = {
                        ...this._manualSlotPins,
                        [String(draggedSlot.id)]: occupyingPlayerId,
                        [String(occupyingSlot.id)]: draggedPlayerId,
                    };
                    // Mirror the swap in startingSlotMap so the no-pending-
                    // subs render path (which reads it directly) reflects
                    // the drag without going through bestFit.
                    this.startingSlotMap = swapSlots(
                        this.startingSlotMap,
                        draggedSlot.id,
                        occupyingSlot.id,
                    );
                },
                swapOnly: true,
                pitchElementId: 'live-pitch-field',
                getPositions: () => this.livePitchPositions,
                setPositions: () => {},
                getFormationGuard: () => ({
                    effective: this.pendingFormation ?? this.activeFormation,
                    tracked: this._pitchPositionsFormation,
                }),
            });
            Object.assign(this, grid);

            // Start polling for career actions completion
            this.startProcessingPoll();

            // Initialize other match scores
            this.otherMatchScores = this.otherMatches.map(() => ({
                homeScore: 0,
                awayScore: 0,
            }));

            // Compute initial player ratings from cached performance data
            // (only if match is already at full time, e.g. page refresh)
            if (this.phase === PHASE.FULL_TIME) {
                this.recalculatePlayerRatings();
            }

            // If ET data was preloaded (page refresh during ET), set it up
            if (this.preloadedExtraTimeData) {
                this.extraTimeEvents = this.preloadedExtraTimeData.extraTimeEvents || [];
                this.etHomeScore = this.preloadedExtraTimeData.homeScoreET || 0;
                this.etAwayScore = this.preloadedExtraTimeData.awayScoreET || 0;
                this.penaltyResult = this.preloadedExtraTimeData.penalties || null;
                this._needsPenalties = this.preloadedExtraTimeData.needsPenalties || false;
            }

            // Pause simulation when the browser tab loses focus so the
            // clock and event list stay in sync (requestAnimationFrame stops
            // firing while the tab is hidden, causing a large time jump on return).
            this._onVisibilityChange = () => {
                if (document.hidden && !this.userPaused && this.phase !== PHASE.FULL_TIME && this.phase !== PHASE.PRE_MATCH) {
                    this.userPaused = true;
                }
            };
            document.addEventListener('visibilitychange', this._onVisibilityChange);

            // On page refresh, try to restore cached events so the feed is
            // identical to what the user saw during the live simulation.
            if (this.animationSeen && this._restoreEventsFromCache()) {
                this.skipToFullTimeImmediate();
            } else {
                // Generate client-side atmosphere events (shots, fouls) and narratives
                this._injectAtmosphere();
                if (this.preloadedExtraTimeData) {
                    this._injectETAtmosphere();
                }

                if (this.animationSeen) {
                    // Cache miss on refresh — synthesize fresh (rare edge case)
                    this.skipToFullTimeImmediate();
                } else {
                    this.startSimulation();
                }
            }
        },

        /**
         * Cold-start the component directly into full_time state without any
         * animation. Used on page refresh so the user sees the final result
         * immediately instead of replaying the live experience.
         */
        skipToFullTimeImmediate() {
            // Synthesize any missing goal events so the event list is complete
            this.events = this.synthesizeGoalsIfNeeded(this.events);

            // Reveal all regular-time events and track substitutions
            for (let i = 0; i < this.events.length; i++) {
                const event = this.events[i];
                this.revealedEvents.unshift(event);
                this.lastRevealedIndex = i;

                if (event.type === 'substitution' && event.teamId === this.userTeamId) {
                    this.substitutionsMade.push({
                        playerOutId: event.gamePlayerId,
                        playerInId: event.metadata?.player_in_id ?? '',
                        minute: event.minute,
                        playerOutName: event.playerName ?? '',
                        playerInName: event.playerInName ?? '',
                    });
                    const playerInId = event.metadata?.player_in_id;
                    if (playerInId) {
                        const benchPlayer = this.benchPlayers.find(p => p.id === playerInId);
                        if (benchPlayer) benchPlayer.minuteEntered = event.minute;
                    }
                }
            }

            // Set regular-time scores
            this.homeScore = this.finalHomeScore;
            this.awayScore = this.finalAwayScore;

            // Handle extra time if preloaded (ET already simulated before refresh)
            if (this.preloadedExtraTimeData) {
                for (let i = 0; i < this.extraTimeEvents.length; i++) {
                    const event = this.extraTimeEvents[i];
                    this.revealedEvents.unshift(event);
                    this.lastRevealedETIndex = i;

                    if (event.type === 'substitution' && event.teamId === this.userTeamId) {
                        this.substitutionsMade.push({
                            playerOutId: event.gamePlayerId,
                            playerInId: event.metadata?.player_in_id ?? '',
                            minute: event.minute,
                            playerOutName: event.playerName ?? '',
                            playerInName: event.playerInName ?? '',
                        });
                    }
                }
                this.homeScore = this.finalHomeScore + this.etHomeScore;
                this.awayScore = this.finalAwayScore + this.etAwayScore;
                this.currentMinute = MINUTE.ET_END;
            } else {
                this.currentMinute = MINUTE.REGULAR_TIME_END;
            }

            // Set other match scores to final values
            for (let i = 0; i < this.otherMatches.length; i++) {
                this.otherMatchScores[i] = {
                    homeScore: this.otherMatches[i].homeScore,
                    awayScore: this.otherMatches[i].awayScore,
                };
            }

            // Set final possession (no oscillation)
            this.homePossession = this._basePossession;
            this.awayPossession = 100 - this._basePossession;

            // Set phase to full_time and calculate player ratings
            this.phase = PHASE.FULL_TIME;
            this.recalculatePlayerRatings();

            // Generate match summary if not restored from cache
            if (!this.matchSummary && typeof this._generateMatchSummary === 'function') {
                this.matchSummary = this._generateMatchSummary();
            }
        },

        _cacheEvents() {
            eventCache.save({
                events: this.events,
                extraTimeEvents: this.extraTimeEvents,
                matchSummary: this.matchSummary,
            });
        },

        _restoreEventsFromCache() {
            const cached = eventCache.restore();
            if (!cached) return false;
            if (cached.events) this.events = cached.events;
            if (cached.extraTimeEvents) this.extraTimeEvents = cached.extraTimeEvents;
            if (cached.matchSummary) this.matchSummary = cached.matchSummary;
            return true;
        },

        _clearEventsCache() {
            eventCache.clear();
        },

        // =====================================================================
        // Match simulation — provided by match-simulation module via Object.assign in init()
        // Methods: startSimulation, togglePause, setSpeed, skipToHalfTime, skipToEnd,
        //          startSecondHalf, startExtraTime, startETSecondHalf, enterFullTime,
        //          synthesizeGoalsIfNeeded, recalculateScore, resetPossessionTarget
        // =====================================================================

        // =============================
        // Penalty picker & shootout — provided by penalty-shootout module via Object.assign in init()
        // Methods: openPenaltyPicker, availablePenaltyPlayers, addPenaltyKicker,
        //          removePenaltyKicker, confirmPenaltyKickers, revealPenaltyKicks,
        //          skipPenaltyReveal, penaltyHomeScore, penaltyAwayScore, penaltyWinner
        // =============================

        // =============================
        // Extra time display helpers
        // =============================

        get hasExtraTime() {
            return this.extraTimeEvents.length > 0 || this.etHomeScore > 0 || this.etAwayScore > 0
                || this._needsPenalties || this.penaltyResult !== null
                || this.preloadedExtraTimeData !== null;
        },

        get isInExtraTime() {
            return isExtraTimePhase(this.phase);
        },

        get totalMinutes() {
            return this.hasExtraTime || this.isInExtraTime ? MINUTE.ET_END : MINUTE.REGULAR_TIME_END;
        },

        // etFirstHalfEvents, etSecondHalfEvents, showETHalfTimeSeparator,
        // showExtraTimeSeparator — provided by event-feed module via mixin.

        // penaltyHomeScore, penaltyAwayScore, penaltyWinner — provided by penalty-shootout module

        // Knockout + tournament outcome getters — provided by knockout-outcome
        // module via Object.assign below: knockoutOutcome, playerWon,
        // tournamentResultType, isTournamentDecisive, isChampion.

        // =============================
        // Tactical panel methods — provided by tactical-panel module via mixin.
        // openTacticalPanel, closeTacticalPanel, safeCloseTacticalPanel,
        // hasSubPendingChanges, hasPendingChanges, hasTacticalChanges,
        // mentalityLabel, getMentalityLabel, getFormationTooltip,
        // getMentalityTooltip, resetTactics, refreshFormationPreview,
        // _postComputeSlots, resetAllChanges, getOptionLabel,
        // confirmationSummary, showConfirmation, cancelConfirmation,
        // _scrollTacticalToTop, _computePickerOffenders,
        // openFormationPicker, _postSubActivePlayerIds,
        // closeFormationPicker, acceptFormationPickerChoice,
        // keepFormationWithPenalty, _advanceToConfirmation.
        //
        // confirmAllChanges, autoSubUserTeamBeforeSkip — provided by
        // tactical-submission module via mixin.
        // =============================

        // =============================
        // Substitution methods — provided by substitution-manager module via Object.assign in init()
        // Getters: redCardedPlayerIds, yellowCardedPlayerIds, effectiveMaxSubstitutions,
        //          effectiveMaxWindows, windowsUsed, hasWindowsLeft, subsRemaining,
        //          canSubstitute, canAddMoreToPending, availableLineupForPicker,
        //          availableBenchForPicker, availableLineupPlayers, availableBenchPlayers
        // Methods: isPlayerYellowCarded, resetSubstitutions, addPendingSub,
        //          removePendingSub, getActiveLineupPlayers
        // =============================


        // recalculateScore — provided by match-simulation module

        // =============================
        // Player ratings — provided by ratings-glue module via mixinModule
        // Methods: recalculatePlayerRatings, ratingColor, getBaseRating,
        //          getEventIcons, getSubMap
        // =============================

        // =============================
        // Display helpers
        // =============================

        // displayMinute, timelineProgress, timelineHalfMarker, timelineETMarker,
        // timelineETHalfMarker, getTimelineMarkers, getEventIcon, getEventSide,
        // isGoalEvent, isAtmosphereEvent — provided by event-feed module.

        get isRunning() {
            return isPlayingPhase(this.phase) && !this.tacticalPanelOpen;
        },

        get phaseLabel() {
            switch (this.phase) {
                case PHASE.GOING_TO_EXTRA_TIME: return this.translations.extraTime || 'Extra Time';
                case PHASE.EXTRA_TIME_HALF_TIME: return this.translations.etHalfTime || 'ET Half Time';
                case PHASE.PENALTIES: return this.translations.penalties || 'Penalties';
                default: return '';
            }
        },

        // _atmosphereConfig, _injectAtmosphere, _injectETAtmosphere,
        // _generateMatchSummary — provided by atmosphere-glue module.

        // =====================================================================
        // Pitch Visualization (shared with lineup page)
        // currentPitchSlots, currentSlots, slotAssignments — provided by
        // pitch-layout module via mixin.
        // =====================================================================

        // getActiveLineupPlayers — provided by substitution-manager module

        getEffectivePosition(slotId) {
            const gc = this.gridConfig;
            if (!gc) return null;
            // Only apply custom positions when the formation matches — slot IDs
            // map to different grid cells per formation.
            const effectiveFormation = this.pendingFormation ?? this.activeFormation;
            const positions = (effectiveFormation === this._pitchPositionsFormation) ? this.livePitchPositions : {};
            return _getEffectivePosition(slotId, positions, this.currentPitchSlots, gc.cols, gc.rows);
        },

        getShirtStyle(role) {
            return _getShirtStyle(role, this.teamColors);
        },

        getNumberStyle(role) {
            return _getNumberStyle(role, this.teamColors);
        },

        getInitials(name) {
            return _getInitials(name);
        },

        /**
         * Handle tapping a player on the pitch to select for substitution.
         */
        handlePitchPlayerClick(slot) {
            // If we just finished a drag, suppress the click
            if (this._wasDragging) {
                this._wasDragging = false;
                return;
            }

            if (!slot.player) return;
            if (this.tacticalTab !== 'substitutions') {
                this.tacticalTab = 'substitutions';
            }

            // Can't select a red-carded player
            if (this.redCardedPlayerIds.includes(slot.player.id)) return;

            // If this player is already selected, deselect
            if (this.livePitchSelectedOutId === slot.player.id) {
                this.livePitchSelectedOutId = null;
                this.selectedPlayerOut = null;
                return;
            }

            // Can't select if no subs/windows left
            if (!this.canSubstitute || !this.hasWindowsLeft) return;

            // Find this player in the lineup players list for the full data object
            const playerData = this.lineupPlayers.find(p => p.id === slot.player.id)
                || this.benchPlayers.find(p => p.id === slot.player.id);

            if (playerData) {
                this.livePitchSelectedOutId = slot.player.id;
                this.selectedPlayerOut = playerData;
                this.selectedPlayerIn = null;
            }
        },

        /**
         * Get energy for a player badge on the pitch (live mode).
         */
        getPitchPlayerEnergy(player) {
            if (!player) return 100;
            // Try to find full player data with minuteEntered
            const fullData = this.lineupPlayers.find(p => p.id === player.id)
                || this.benchPlayers.find(p => p.id === player.id);
            if (!fullData) return 100;
            return _getPlayerEnergy(fullData, this.currentMinute);
        },

        /**
         * Get energy bar color class for pitch display.
         */
        getPitchEnergyColor(player) {
            const energy = this.getPitchPlayerEnergy(player);
            return _getEnergyColor(energy);
        },

        getPositionBadgeColor(group) {
            return _getPositionBadgeColor(group);
        },

        getSecondaryBadgeClasses(position) {
            return _getSecondaryBadgeClasses(position);
        },

        getSecondaryAbbr(position) {
            return _getSecondaryAbbr(position);
        },

        getOvrBadgeClasses(score) {
            return _getOvrBadgeClasses(score);
        },

        getRatingBadgeClass(value) {
            if (value >= 80) return 'rating-elite';
            if (value >= 70) return 'rating-good';
            if (value >= 60) return 'rating-average';
            if (value >= 50) return 'rating-below';
            return 'rating-poor';
        },

        // =====================================================================
        // Pitch Drag & Repositioning — provided by pitch-grid module via Object.assign in init()
        // Methods: getSlotCell, isCellOccupied, selectForRepositioning,
        //          setSlotGridPosition, handleGridCellClick, getGridCellState,
        //          startDrag, _findSlotAtCell, _getPitchElement, _wasDragging
        // =====================================================================

        isValidGridCell(slotLabel, col, row) {
            return _isValidGridCell(slotLabel, col, row, this.gridConfig);
        },

        getZoneColorClass(role) {
            return _getZoneColorClass(role);
        },

        // getStatCount — provided by event-feed module.
        // Synthetic stats (passes, corners, offsides, fouls) live in
        // `modules/match-stats.js` and are mixed into the component below.

        // =============================
        // Energy / Stamina — delegated to pitch-renderer
        // =============================

        calculateDrainRate(physicalAbility, age, positionGroup) {
            return _calculateDrainRate(physicalAbility, age, positionGroup);
        },

        getPlayerEnergy(player) {
            return _getPlayerEnergy(player, this.currentMinute);
        },

        getEnergyColor(energy) {
            return _getEnergyColor(energy);
        },

        getEnergyBarBg(energy) {
            return _getEnergyBarBg(energy);
        },

        getEnergyTextColor(energy) {
            return _getEnergyTextColor(energy);
        },

        // firstHalfEvents, secondHalfEvents, showHalfTimeSeparator,
        // groupSubstitutions, getTimelineMarkers — provided by event-feed.

        startProcessingPoll() {
            if (!this.processingStatusUrl) {
                this.processingReady = true;
                return;
            }

            const check = async () => {
                try {
                    const response = await fetch(this.processingStatusUrl);
                    const data = await response.json();
                    if (data.ready) {
                        this.processingReady = true;
                        clearInterval(this._processingPollTimer);
                        this._processingPollTimer = null;
                    }
                } catch (e) {
                    // Silently retry on next interval
                }
            };

            // Career actions run in background while user watches the match.
            // Delay first check and poll every 3s — the job usually finishes
            // well before the match animation ends (~15-30s).
            setTimeout(() => {
                check();
                this._processingPollTimer = setInterval(check, 3000);
            }, 3000);
        },

        destroy() {
            this._destroySimulationTimers();
            clearTimeout(this.pauseTimer);
            this._destroyPenaltyTimers();
            clearInterval(this._processingPollTimer);
            document.body.classList.remove('overflow-y-hidden');
            if (this._onVisibilityChange) {
                document.removeEventListener('visibilitychange', this._onVisibilityChange);
            }
        },
    };

    // Mix module members (including getters) into the raw component object
    // BEFORE Alpine wraps it, so Alpine's reactivity sees them natively.
    //
    // Composition order: earlier entries are overwritten by later ones when
    // names collide. Current layout has no intentional overrides — module
    // APIs are disjoint. If a future collision is intentional, document it
    // alongside the call.
    mixinModule(component, subs);
    mixinModule(component, penalties);
    mixinModule(component, sim);
    mixinModule(component, stats);
    mixinModule(component, knockoutOutcome);
    mixinModule(component, ratings);
    mixinModule(component, eventFeed);
    mixinModule(component, atmosphere);
    mixinModule(component, tacticalPanel);
    mixinModule(component, tacticalSubmission);
    mixinModule(component, pitchLayout);

    return component;
}
