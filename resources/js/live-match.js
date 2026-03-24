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
    isValidGridCell as _isValidGridCell,
    getZoneColorClass as _getZoneColorClass,
} from './modules/pitch-renderer.js';
import { createPitchGrid } from './modules/pitch-grid.js';
import { assignPlayersToSlots } from './modules/slot-assignment.js';
import { createPenaltyShootout } from './modules/penalty-shootout.js';
import { createSubstitutionManager } from './modules/substitution-manager.js';
import { createMatchSimulation } from './modules/match-simulation.js';

export default function liveMatch(config) {
    return {
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
        tacticalActionsUrl: config.tacticalActionsUrl || '',
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
        activeFormation: config.activeFormation || '4-4-2',
        activeMentality: config.activeMentality || 'balanced',
        activePlayingStyle: config.activePlayingStyle || 'balanced',
        activePressing: config.activePressing || 'standard',
        activeDefLine: config.activeDefLine || 'normal',
        availableFormations: config.availableFormations || [],
        availableMentalities: config.availableMentalities || [],
        availablePlayingStyles: config.availablePlayingStyles || [],
        availablePressing: config.availablePressing || [],
        availableDefLine: config.availableDefLine || [],
        translations: config.translations || {},

        // Extra time / knockout config
        isKnockout: config.isKnockout || false,
        extraTimeUrl: config.extraTimeUrl || '',
        penaltiesUrl: config.penaltiesUrl || '',
        twoLeggedInfo: config.twoLeggedInfo || null,
        preloadedExtraTimeData: config.extraTimeData || null,

        // Tournament knockout config
        isTournamentKnockout: config.isTournamentKnockout || false,
        knockoutRoundNumber: config.knockoutRoundNumber || null,
        knockoutRoundName: config.knockoutRoundName || '',

        // MVP
        mvpPlayerName: config.mvpPlayerName || null,
        mvpPlayerTeamId: config.mvpPlayerTeamId || null,

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
        _pitchPositionsFormation: config.activeFormation || '4-4-2',
        manualAssignments: config.manualAssignments || {},
        _savedPitchPositions: config.pitchPositions ? { ...config.pitchPositions } : {},
        _positionJustApplied: false,

        // Tactical change state
        pendingFormation: null,
        pendingMentality: null,
        pendingPlayingStyle: null,
        pendingPressing: null,
        pendingDefLine: null,
        applyingChanges: false,
        showingConfirmation: false,

        // Tab state
        activeTab: 'events',

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
        substitutionsMade: config.existingSubstitutions
            ? config.existingSubstitutions.map(s => ({
                playerOutId: s.player_out_id,
                playerInId: s.player_in_id,
                minute: s.minute,
                playerOutName: '',
                playerInName: '',
            }))
            : [],

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
            // Integrate shared pitch grid module (positioning + drag-and-drop)
            const grid = createPitchGrid(() => this, {
                allowGkDrag: false,
                allowGkReposition: false,
                allowGkSwap: false,
                dragThreshold: 5,
                onTapFallback: (slot) => this.handlePitchPlayerClick(slot),
                onPositionChanged: (positions) => {
                    this._pitchPositionsFormation = this.pendingFormation ?? this.activeFormation;
                    this._savedPitchPositions = JSON.parse(JSON.stringify(positions));
                    this._positionJustApplied = true;
                },
                pitchElementId: 'live-pitch-field',
                getPositions: () => this.livePitchPositions,
                setPositions: (p) => { this.livePitchPositions = p },
                getFormationGuard: () => ({
                    effective: this.pendingFormation ?? this.activeFormation,
                    tracked: this._pitchPositionsFormation,
                }),
            });
            Object.assign(this, grid);

            // Integrate penalty shootout module
            const penalties = createPenaltyShootout(() => this);
            Object.assign(this, penalties);

            // Integrate substitution manager module
            const subs = createSubstitutionManager(() => this);
            Object.assign(this, subs);

            // Integrate match simulation module (clock, events, ET, possession)
            const sim = createMatchSimulation(() => this);
            Object.assign(this, sim);

            // Start polling for career actions completion
            this.startProcessingPoll();

            // Initialize other match scores
            this.otherMatchScores = this.otherMatches.map(() => ({
                homeScore: 0,
                awayScore: 0,
            }));

            // If ET data was preloaded (page refresh during ET), set it up
            if (this.preloadedExtraTimeData) {
                this.extraTimeEvents = this.preloadedExtraTimeData.extraTimeEvents || [];
                this.etHomeScore = this.preloadedExtraTimeData.homeScoreET || 0;
                this.etAwayScore = this.preloadedExtraTimeData.awayScoreET || 0;
                this.penaltyResult = this.preloadedExtraTimeData.penalties || null;
                this._needsPenalties = this.preloadedExtraTimeData.needsPenalties || false;
            }

            // Start the match simulation (synthesize goals + kickoff delay)
            this.startSimulation();
        },

        // =====================================================================
        // Match simulation — provided by match-simulation module via Object.assign in init()
        // Methods: startSimulation, togglePause, setSpeed, skipToEnd, enterFullTime,
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
            return this.phase === 'going_to_extra_time'
                || this.phase === 'extra_time_first_half'
                || this.phase === 'extra_time_half_time'
                || this.phase === 'extra_time_second_half';
        },

        get totalMinutes() {
            return this.hasExtraTime || this.isInExtraTime ? 120 : 90;
        },

        get etFirstHalfEvents() {
            return this.revealedEvents.filter(e => e.minute > 90 && e.minute <= 105);
        },

        get etSecondHalfEvents() {
            return this.revealedEvents.filter(e => e.minute > 105);
        },

        get showETHalfTimeSeparator() {
            return this.phase === 'extra_time_half_time'
                || this.phase === 'extra_time_second_half'
                || ((this.phase === 'penalties' || this.phase === 'full_time') && this.hasExtraTime);
        },

        get showExtraTimeSeparator() {
            return this.isInExtraTime || this.phase === 'penalties'
                || (this.phase === 'full_time' && this.hasExtraTime);
        },

        // penaltyHomeScore, penaltyAwayScore, penaltyWinner — provided by penalty-shootout module

        // =============================
        // Knockout outcome (works for all knockout matches, not just tournament)
        // Returns 'win', 'loss', or null (for non-knockout / league draws)
        get knockoutOutcome() {
            if (!this.isKnockout) return null;

            // Penalties decide the winner
            if (this.penaltyResult) {
                const penHome = this.penaltyResult.home;
                const penAway = this.penaltyResult.away;
                const homeWon = penHome > penAway;
                const userWon = this.userTeamId === this.homeTeamId ? homeWon : !homeWon;
                return userWon ? 'win' : 'loss';
            }

            // ET or regular time
            if (this.homeScore === this.awayScore) return null;
            const homeWon = this.homeScore > this.awayScore;
            const userWon = this.userTeamId === this.homeTeamId ? homeWon : !homeWon;
            return userWon ? 'win' : 'loss';
        },

        // Tournament result helpers
        // =============================

        get playerWon() {
            if (!this.isTournamentKnockout) return null;
            // Penalties decide the winner if they were played
            if (this.penaltyResult) {
                const penHome = this.penaltyResult.home;
                const penAway = this.penaltyResult.away;
                const homeWon = penHome > penAway;
                return this.userTeamId === this.homeTeamId ? homeWon : !homeWon;
            }
            // ET or regular time: compare displayed scores at full time
            const home = this.homeScore;
            const away = this.awayScore;
            if (home === away) return null; // shouldn't happen in knockout without pens
            const homeWon = home > away;
            return this.userTeamId === this.homeTeamId ? homeWon : !homeWon;
        },

        get tournamentResultType() {
            if (!this.isTournamentKnockout || this.playerWon === null) return null;
            const round = this.knockoutRoundNumber;
            const won = this.playerWon;

            // Round 6 = Final
            if (round === 6) return won ? 'champion' : 'runner_up';
            // Round 5 = Third-place match
            if (round === 5) return won ? 'third' : 'fourth';
            // Round 4 = Semi-final
            if (round === 4) return won ? 'to_final' : 'to_third_place';
            // Rounds 1-3 = R32/R16/QF
            return won ? 'advance' : 'eliminated';
        },

        get isTournamentDecisive() {
            const type = this.tournamentResultType;
            if (!type) return false;
            // Decisive = tournament is over for the player (no more matches after this)
            return ['champion', 'runner_up', 'third', 'fourth', 'eliminated'].includes(type);
        },

        get isChampion() {
            return this.tournamentResultType === 'champion';
        },

        // =============================
        // Tactical panel methods
        // =============================

        openTacticalPanel(tab = 'substitutions', keepInjuryAlert = false) {
            this.tacticalTab = tab;
            this.tacticalPanelOpen = true;
            this.selectedPlayerOut = null;
            this.selectedPlayerIn = null;
            this.livePitchSelectedOutId = null;
            this.pendingSubs = [];
            this.pendingFormation = null;
            this.pendingMentality = null;
            if (!keepInjuryAlert) {
                this.injuryAlertPlayer = null;
            }
            document.body.classList.add('overflow-y-hidden');
        },

        closeTacticalPanel() {
            this.tacticalPanelOpen = false;
            this.selectedPlayerOut = null;
            this.selectedPlayerIn = null;
            this.livePitchSelectedOutId = null;
            this.pendingSubs = [];
            this.pendingFormation = null;
            this.pendingMentality = null;
            this.draggingSlotId = null;
            this.dragPosition = null;
            this.positioningSlotId = null;
            this.injuryAlertPlayer = null;
            this._positionJustApplied = false;
            this.showingConfirmation = false;
            document.body.classList.remove('overflow-y-hidden');
        },

        get hasSubPendingChanges() {
            return this.pendingSubs.length > 0
                || (this.selectedPlayerOut !== null && this.selectedPlayerIn !== null);
        },

        get hasPendingChanges() {
            return this.hasSubPendingChanges || this.hasTacticalChanges;
        },

        safeCloseTacticalPanel() {
            if (this.hasPendingChanges) {
                if (!confirm(this.translations?.unsavedTacticalChanges ?? 'You have unsubmitted changes. Close anyway?')) {
                    return;
                }
            }
            this.closeTacticalPanel();
        },

        get mentalityLabel() {
            const m = this.availableMentalities.find(m => m.value === this.activeMentality);
            return m ? m.label : this.activeMentality;
        },

        get hasTacticalChanges() {
            return (this.pendingFormation !== null && this.pendingFormation !== this.activeFormation)
                || (this.pendingMentality !== null && this.pendingMentality !== this.activeMentality)
                || (this.pendingPlayingStyle !== null && this.pendingPlayingStyle !== this.activePlayingStyle)
                || (this.pendingPressing !== null && this.pendingPressing !== this.activePressing)
                || (this.pendingDefLine !== null && this.pendingDefLine !== this.activeDefLine);
        },

        getMentalityLabel(value) {
            const m = this.availableMentalities.find(m => m.value === value);
            return m ? m.label : value;
        },

        getFormationTooltip() {
            const selected = this.pendingFormation ?? this.activeFormation;
            const f = this.availableFormations.find(f => f.value === selected);
            return f ? f.tooltip : '';
        },

        getMentalityTooltip(value) {
            const m = this.availableMentalities.find(m => m.value === value);
            return m ? m.tooltip : '';
        },

        resetTactics() {
            this.pendingFormation = null;
            this.pendingMentality = null;
            this.pendingPlayingStyle = null;
            this.pendingPressing = null;
            this.pendingDefLine = null;
        },

        resetAllChanges() {
            this.resetSubstitutions();
            this.resetTactics();
            this.showingConfirmation = false;
        },

        getOptionLabel(options, value) {
            const opt = options.find(o => o.value === value);
            return opt ? opt.label : value;
        },

        get confirmationSummary() {
            const summary = { subs: [], tactics: [] };

            // Pending subs
            for (const sub of this.pendingSubs) {
                summary.subs.push({
                    playerOut: sub.playerOut.name,
                    playerOutAbbr: sub.playerOut.positionAbbr,
                    playerOutGroup: sub.playerOut.positionGroup,
                    playerIn: sub.playerIn.name,
                    playerInAbbr: sub.playerIn.positionAbbr,
                    playerInGroup: sub.playerIn.positionGroup,
                });
            }

            // Also include auto-added pair if present
            if (this.selectedPlayerOut && this.selectedPlayerIn) {
                const alreadyPending = summary.subs.some(
                    s => s.playerOut === this.selectedPlayerOut.name && s.playerIn === this.selectedPlayerIn.name
                );
                if (!alreadyPending) {
                    summary.subs.push({
                        playerOut: this.selectedPlayerOut.name,
                        playerOutAbbr: this.selectedPlayerOut.positionAbbr,
                        playerOutGroup: this.selectedPlayerOut.positionGroup,
                        playerIn: this.selectedPlayerIn.name,
                        playerInAbbr: this.selectedPlayerIn.positionAbbr,
                        playerInGroup: this.selectedPlayerIn.positionGroup,
                    });
                }
            }

            // Tactical changes
            if (this.pendingFormation !== null && this.pendingFormation !== this.activeFormation) {
                summary.tactics.push({
                    label: this.translations.confirmFormation ?? 'Formation',
                    from: this.activeFormation,
                    to: this.pendingFormation,
                });
            }
            if (this.pendingMentality !== null && this.pendingMentality !== this.activeMentality) {
                summary.tactics.push({
                    label: this.translations.confirmMentality ?? 'Mentality',
                    from: this.getOptionLabel(this.availableMentalities, this.activeMentality),
                    to: this.getOptionLabel(this.availableMentalities, this.pendingMentality),
                });
            }
            if (this.pendingPlayingStyle !== null && this.pendingPlayingStyle !== this.activePlayingStyle) {
                summary.tactics.push({
                    label: this.translations.confirmPlayingStyle ?? 'Playing style',
                    from: this.getOptionLabel(this.availablePlayingStyles, this.activePlayingStyle),
                    to: this.getOptionLabel(this.availablePlayingStyles, this.pendingPlayingStyle),
                });
            }
            if (this.pendingPressing !== null && this.pendingPressing !== this.activePressing) {
                summary.tactics.push({
                    label: this.translations.confirmPressing ?? 'Pressing',
                    from: this.getOptionLabel(this.availablePressing, this.activePressing),
                    to: this.getOptionLabel(this.availablePressing, this.pendingPressing),
                });
            }
            if (this.pendingDefLine !== null && this.pendingDefLine !== this.activeDefLine) {
                summary.tactics.push({
                    label: this.translations.confirmDefLine ?? 'Defensive line',
                    from: this.getOptionLabel(this.availableDefLine, this.activeDefLine),
                    to: this.getOptionLabel(this.availableDefLine, this.pendingDefLine),
                });
            }

            return summary;
        },

        showConfirmation() {
            this.showingConfirmation = true;
        },

        cancelConfirmation() {
            this.showingConfirmation = false;
        },

        async confirmAllChanges() {
            // Auto-add selected pair to pending if present
            if (this.selectedPlayerOut && this.selectedPlayerIn) {
                this.addPendingSub();
            }

            if (!this.hasPendingChanges || this.applyingChanges) return;
            this.applyingChanges = true;

            const minute = Math.floor(this.currentMinute);

            try {
                const payload = {
                    minute,
                    previousSubstitutions: this.substitutionsMade.map(s => ({
                        playerOutId: s.playerOutId,
                        playerInId: s.playerInId,
                        minute: s.minute,
                    })),
                };

                // Include subs if any
                if (this.pendingSubs.length > 0) {
                    payload.substitutions = this.pendingSubs.map(s => ({
                        playerOutId: s.playerOut.id,
                        playerInId: s.playerIn.id,
                    }));
                }

                // Include pitch positions if any were customized
                if (Object.keys(this.livePitchPositions).length > 0) {
                    payload.pitch_positions = this.livePitchPositions;
                }

                // Include tactical changes if any
                if (this.hasTacticalChanges) {
                    if (this.pendingFormation !== null && this.pendingFormation !== this.activeFormation) {
                        payload.formation = this.pendingFormation;
                    }
                    if (this.pendingMentality !== null && this.pendingMentality !== this.activeMentality) {
                        payload.mentality = this.pendingMentality;
                    }
                    if (this.pendingPlayingStyle !== null && this.pendingPlayingStyle !== this.activePlayingStyle) {
                        payload.playing_style = this.pendingPlayingStyle;
                    }
                    if (this.pendingPressing !== null && this.pendingPressing !== this.activePressing) {
                        payload.pressing = this.pendingPressing;
                    }
                    if (this.pendingDefLine !== null && this.pendingDefLine !== this.activeDefLine) {
                        payload.defensive_line = this.pendingDefLine;
                    }
                }

                const response = await fetch(this.tacticalActionsUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                if (!response.ok) {
                    const error = await response.json();
                    console.error('Tactical actions failed:', error);
                    this.applyingChanges = false;
                    return;
                }

                const result = await response.json();
                const isET = result.isExtraTime || false;

                // Record substitutions if any
                if (result.substitutions && result.substitutions.length > 0) {
                    for (const sub of result.substitutions) {
                        this.substitutionsMade.push({
                            playerOutId: sub.playerOutId,
                            playerInId: sub.playerInId,
                            playerOutName: sub.playerOutName,
                            playerInName: sub.playerInName,
                            minute,
                        });

                        const benchPlayer = this.benchPlayers.find(p => p.id === sub.playerInId);
                        if (benchPlayer) {
                            benchPlayer.minuteEntered = minute;
                        }
                    }
                }

                // Update active tactics
                if (result.formation) {
                    const formationChanged = result.formation !== this.activeFormation;
                    this.activeFormation = result.formation;
                    if (formationChanged) {
                        // Only clear positions if they belong to the old formation —
                        // the user may have already dragged players during preview.
                        if (this._pitchPositionsFormation !== result.formation) {
                            this.livePitchPositions = {};
                            this._savedPitchPositions = {};
                        }
                        this._pitchPositionsFormation = result.formation;
                        this.manualAssignments = {};
                    }
                }
                if (result.mentality) {
                    this.activeMentality = result.mentality;
                }
                if (result.playingStyle) {
                    this.activePlayingStyle = result.playingStyle;
                }
                if (result.pressing) {
                    this.activePressing = result.pressing;
                }
                if (result.defensiveLine) {
                    this.activeDefLine = result.defensiveLine;
                }

                // Filter events up to current minute and add sub events to feed
                if (isET) {
                    this.extraTimeEvents = this.extraTimeEvents.filter(e => e.minute <= minute);
                } else {
                    this.events = this.events.filter(e => e.minute <= minute);
                }
                this.revealedEvents = this.revealedEvents.filter(e => e.minute <= minute);

                if (result.substitutions) {
                    for (const sub of result.substitutions) {
                        this.revealedEvents.unshift({
                            minute,
                            type: 'substitution',
                            playerName: sub.playerOutName,
                            playerInName: sub.playerInName,
                            teamId: sub.teamId,
                        });
                    }
                }

                // Append new events and update scores
                if (isET) {
                    if (result.newEvents && result.newEvents.length > 0) {
                        this.extraTimeEvents.push(...result.newEvents);
                        this.extraTimeEvents.sort((a, b) => a.minute - b.minute);
                    }

                    this.lastRevealedETIndex = -1;
                    for (let i = 0; i < this.extraTimeEvents.length; i++) {
                        if (this.extraTimeEvents[i].minute <= this.currentMinute) {
                            this.lastRevealedETIndex = i;
                        } else {
                            break;
                        }
                    }

                    this.etHomeScore = result.newScore.home;
                    this.etAwayScore = result.newScore.away;
                    this._needsPenalties = result.needsPenalties || false;
                } else {
                    if (result.newEvents && result.newEvents.length > 0) {
                        this.events.push(...result.newEvents);
                        this.events.sort((a, b) => a.minute - b.minute);
                    }

                    this.lastRevealedIndex = -1;
                    for (let i = 0; i < this.events.length; i++) {
                        if (this.events[i].minute <= this.currentMinute) {
                            this.lastRevealedIndex = i;
                        } else {
                            break;
                        }
                    }

                    this.finalHomeScore = result.newScore.home;
                    this.finalAwayScore = result.newScore.away;

                    this.events = this.synthesizeGoalsIfNeeded(this.events);
                }

                this.recalculateScore();

                // Update possession
                if (result.homePossession !== undefined) {
                    this._basePossession = result.homePossession;
                    this._possessionDisplay = result.homePossession;
                    this.homePossession = result.homePossession;
                    this.awayPossession = result.awayPossession;
                    this.resetPossessionTarget();
                }

                // Close the panel and resume
                this.closeTacticalPanel();
            } catch (err) {
                console.error('Tactical actions request failed:', err);
            } finally {
                this.applyingChanges = false;
            }
        },

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
        // Display helpers
        // =============================

        get displayMinute() {
            const m = Math.floor(this.currentMinute);
            if (this.phase === 'pre_match') return '0';
            if (this.phase === 'half_time') return '45';
            if (this.phase === 'going_to_extra_time') return '90';
            if (this.phase === 'extra_time_half_time') return '105';
            if (this.phase === 'penalties') return '120';
            if (this.phase === 'full_time') {
                return this.hasExtraTime ? '120' : '90';
            }
            if (this.phase === 'extra_time_first_half' || this.phase === 'extra_time_second_half') {
                return String(Math.min(m, 120));
            }
            return String(Math.min(m, 90));
        },

        get timelineProgress() {
            const total = this.totalMinutes;
            return Math.min((this.currentMinute / total) * 100, 100);
        },

        get timelineHalfMarker() {
            return this.totalMinutes === 120 ? (45 / 120) * 100 : 50;
        },

        get timelineETMarker() {
            return (90 / 120) * 100;
        },

        get timelineETHalfMarker() {
            return (105 / 120) * 100;
        },

        get isRunning() {
            return (this.phase === 'first_half' || this.phase === 'second_half'
                || this.phase === 'extra_time_first_half' || this.phase === 'extra_time_second_half')
                && !this.tacticalPanelOpen;
        },

        get phaseLabel() {
            switch (this.phase) {
                case 'going_to_extra_time': return this.translations.extraTime || 'Extra Time';
                case 'extra_time_half_time': return this.translations.etHalfTime || 'ET Half Time';
                case 'penalties': return this.translations.penalties || 'Penalties';
                default: return '';
            }
        },

        getEventIcon(type) {
            switch (type) {
                case 'goal': return '\u26BD';
                case 'own_goal': return '\u26BD';
                case 'yellow_card': return '\uD83D\uDFE8';
                case 'red_card': return '\uD83D\uDFE5';
                case 'injury': return '\uD83C\uDFE5';
                case 'substitution': return '\uD83D\uDD04';
                default: return '\u2022';
            }
        },

        getEventSide(event) {
            if (event.type === 'own_goal') {
                return event.teamId === this.homeTeamId ? 'away' : 'home';
            }
            return event.teamId === this.homeTeamId ? 'home' : 'away';
        },

        isGoalEvent(event) {
            return event.type === 'goal' || event.type === 'own_goal';
        },

        // =====================================================================
        // Pitch Visualization (shared with lineup page)
        // =====================================================================

        get currentPitchSlots() {
            const formation = this.pendingFormation ?? this.activeFormation;
            return this.formationSlots[formation] || [];
        },

        // Alias for pitch-display component compatibility (lineup uses currentSlots)
        get currentSlots() {
            return this.currentPitchSlots;
        },

        /**
         * Computed slot assignments for the pitch display.
         * Maps current lineup players onto formation slots,
         * then previews pending substitutions inline.
         */
        get slotAssignments() {
            const slots = this.currentPitchSlots.map(slot => ({
                ...slot,
                player: null,
                compatibility: 0,
                isManual: false,
            }));

            // Build current active lineup (initial + substitutions applied)
            const activeLineup = this.getActiveLineupPlayers();

            // Skip manual assignments when previewing a different formation — slot IDs
            // are formation-specific, so old mappings would place players incorrectly.
            const effectiveFormation = this.pendingFormation ?? this.activeFormation;
            const useManualAssignments = (effectiveFormation === this._pitchPositionsFormation) ? this.manualAssignments : {};
            const assignments = assignPlayersToSlots(slots, activeLineup, this.slotCompatibility, useManualAssignments);

            // Preview pending subs on the pitch: swap out → in
            const allPendingSubs = [...this.pendingSubs];
            if (this.selectedPlayerOut && this.selectedPlayerIn) {
                const alreadyPending = allPendingSubs.some(
                    s => s.playerOut.id === this.selectedPlayerOut.id
                );
                if (!alreadyPending) {
                    allPendingSubs.push({
                        playerOut: this.selectedPlayerOut,
                        playerIn: this.selectedPlayerIn,
                    });
                }
            }

            for (const sub of allPendingSubs) {
                const slot = assignments.find(s => s.player?.id === sub.playerOut.id);
                if (slot) {
                    slot.player = { ...sub.playerIn, isPendingSub: true };
                }
            }

            return assignments;
        },

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
            return getEnergyColor(energy);
        },

        getPositionBadgeColor(group) {
            return _getPositionBadgeColor(group);
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

        confirmPositionChange() {
            this._positionJustApplied = false;
        },

        getStatCount(type, side) {
            const allEvents = [...this.revealedEvents, ...this.extraTimeEvents.filter(e => this.revealedEvents.length >= this.events.length)];
            return allEvents.filter(event => {
                if (event.type !== type) return false;
                const eventSide = this.getEventSide(event);
                return eventSide === side;
            }).length;
        },

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

        get secondHalfEvents() {
            return this.revealedEvents.filter(e => e.minute > 45 && e.minute <= 90);
        },

        get firstHalfEvents() {
            return this.revealedEvents.filter(e => e.minute <= 45);
        },

        get showHalfTimeSeparator() {
            return this.phase === 'half_time' || this.phase === 'second_half' || this.phase === 'full_time'
                || this.isInExtraTime || this.phase === 'going_to_extra_time' || this.phase === 'penalties';
        },

        getTimelineMarkers() {
            const total = this.totalMinutes;
            return this.revealedEvents
                .filter(e => e.type !== 'assist')
                .map(e => ({
                    position: Math.min((e.minute / total) * 100, 100),
                    type: e.type,
                    minute: e.minute,
                }));
        },

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
        },
    };
}
