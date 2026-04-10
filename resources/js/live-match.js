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
import {
    applySubstitution,
    buildSlotView,
    getPlayerCompatibility,
    placeInFirstMatchingSlot,
} from './modules/slot-map.js';
import { createPenaltyShootout } from './modules/penalty-shootout.js';
import { createSubstitutionManager } from './modules/substitution-manager.js';
import { createMatchSimulation } from './modules/match-simulation.js';
import { generateRegularTimeAtmosphere, generateExtraTimeAtmosphere, generateAtmosphereForPeriod, addGoalNarratives, generateContextualNarratives, generateTacticalNarratives } from './modules/atmosphere-generator.js';
import { calculatePlayerRatings, ratingColor as _ratingColor, updateRosterPerformances, countEvents, buildSubstitutionMap } from './modules/player-ratings.js';

/**
 * Copy all own properties from source to target. Regular properties are
 * assigned normally (compatible with Alpine's reactive proxy), while
 * getter/setter descriptors are defined via Object.defineProperty so
 * they remain live computed properties instead of being evaluated once.
 */
function mixinModule(target, source) {
    for (const key of Object.keys(source)) {
        const desc = Object.getOwnPropertyDescriptor(source, key);
        if (desc.get || desc.set) {
            Object.defineProperty(target, key, desc);
        } else {
            target[key] = desc.value;
        }
    }
}

export default function liveMatch(config) {
    // Create modules early with a deferred context reference so their
    // getters are defined on the raw data object BEFORE Alpine wraps it.
    // This ensures Alpine's reactivity system sees them as native getters.
    let _self = null;
    const ctx = () => _self;
    const subs = createSubstitutionManager(ctx);
    const penalties = createPenaltyShootout(ctx);
    const sim = createMatchSimulation(ctx);

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
        homeArticle: config.homeArticle !== undefined ? config.homeArticle : 'el',
        awayArticle: config.awayArticle !== undefined ? config.awayArticle : 'el',
        narrativeTemplates: config.narrativeTemplates || {},

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

            // Start polling for career actions completion
            this.startProcessingPoll();

            // Initialize other match scores
            this.otherMatchScores = this.otherMatches.map(() => ({
                homeScore: 0,
                awayScore: 0,
            }));

            // Compute initial player ratings from cached performance data
            // (only if match is already at full time, e.g. page refresh)
            if (this.phase === 'full_time') {
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
                if (document.hidden && !this.userPaused && this.phase !== 'full_time' && this.phase !== 'pre_match') {
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
                this.currentMinute = 120;
            } else {
                this.currentMinute = 90;
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
            this.phase = 'full_time';
            this.recalculatePlayerRatings();
        },

        /**
         * Cache the current events arrays to localStorage so page refreshes
         * show the exact same event feed the user saw during live simulation.
         */
        _cacheEvents() {
            if (!this.matchId) return;
            try {
                const payload = {
                    events: this.events,
                    extraTimeEvents: this.extraTimeEvents,
                };
                localStorage.setItem(`live_match_events:${this.matchId}`, JSON.stringify(payload));
            } catch (_) { /* quota exceeded — silently skip */ }
        },

        /**
         * Restore cached events from localStorage. Returns true if cache was
         * found and applied, false otherwise.
         */
        _restoreEventsFromCache() {
            if (!this.matchId) return false;
            try {
                const raw = localStorage.getItem(`live_match_events:${this.matchId}`);
                if (!raw) return false;
                const cached = JSON.parse(raw);
                if (cached.events) this.events = cached.events;
                if (cached.extraTimeEvents) this.extraTimeEvents = cached.extraTimeEvents;
                return true;
            } catch (_) { return false; }
        },

        /**
         * Remove the cached events entry for this match.
         */
        _clearEventsCache() {
            if (!this.matchId) return;
            localStorage.removeItem(`live_match_events:${this.matchId}`);
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
            return this.phase === 'going_to_extra_time'
                || this.phase === 'extra_time_first_half'
                || this.phase === 'extra_time_half_time'
                || this.phase === 'extra_time_second_half';
        },

        get totalMinutes() {
            return this.hasExtraTime || this.isInExtraTime ? 120 : 90;
        },

        get etFirstHalfEvents() {
            return this.groupSubstitutions(this.revealedEvents.filter(e => e.minute > 90 && e.minute <= 105));
        },

        get etSecondHalfEvents() {
            return this.groupSubstitutions(this.revealedEvents.filter(e => e.minute > 105));
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
            this.previewSlotMap = null;
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
            this.previewSlotMap = null;
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
            this.previewSlotMap = null;
        },

        /**
         * Fired by the tactical-lever callback whenever the user clicks a
         * formation button in the tactical panel. Hits the backend's
         * compute-slots endpoint with the current on-pitch 11 + the new
         * formation, and stores the resulting slot map in `previewSlotMap`.
         * The `slotAssignments` getter then renders the pitch from it.
         *
         * Uses the same endpoint as the lineup page's formation change, so
         * the placement algorithm is exactly one thing in exactly one place.
         * Previous live-match previews used a naive client-side heuristic
         * that mis-handled players whose primary position had no matching
         * slot in the new formation (e.g. a Defensive Midfielder in a 4-3-3,
         * which has no DM slot).
         *
         * Uses a monotonic `_previewFetchId` to ignore stale responses when
         * the user clicks multiple formations in quick succession.
         */
        async refreshFormationPreview() {
            // If the pending formation matches the active one, we're not
            // previewing anything — drop any cached preview.
            if (this.pendingFormation === null || this.pendingFormation === this.activeFormation) {
                this.previewSlotMap = null;
                return;
            }
            if (!this.computeSlotsUrl) {
                return;
            }

            // Clear the previous preview so the getter falls back to the
            // local placeholder during the fetch window.
            this.previewSlotMap = null;

            const fetchId = ++this._previewFetchId;
            const targetFormation = this.pendingFormation;
            const playerIds = this.getActiveLineupPlayers().map(p => p.id);

            if (playerIds.length === 0) {
                return;
            }

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? this.csrfToken ?? '';
                const response = await fetch(this.computeSlotsUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        formation: targetFormation,
                        player_ids: playerIds,
                        manual_assignments: {},
                    }),
                });
                if (!response.ok) return;
                // Ignore stale responses from superseded clicks.
                if (fetchId !== this._previewFetchId) return;
                // Ignore responses for a formation the user already moved off.
                if (this.pendingFormation !== targetFormation) return;

                const data = await response.json();
                this.previewSlotMap = data.slot_assignments ?? {};
            } catch (e) {
                console.error('Failed to compute formation preview', e);
            }
        },

        resetAllChanges() {
            this.resetSubstitutions();
            this.resetTactics();
            this.showingConfirmation = false;
            this.tacticalError = null;
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
            this.tacticalError = null;
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

            if (this.applyingChanges) return;

            if (!this.hasPendingChanges) {
                if (this.showingConfirmation) {
                    this.tacticalError = this.translations.tacticalErrorNoPending
                        || 'No changes to apply.';
                    this.showingConfirmation = false;
                }
                return;
            }

            this.tacticalError = null;
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
                    let errorMessage = this.translations.tacticalErrorGeneric
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
                    this.tacticalError = errorMessage;
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
                        // Promote the preview map (computed by the backend
                        // during refreshFormationPreview) to the new
                        // authoritative starting map. If we didn't preview,
                        // use whatever the server returned, falling back to
                        // empty — the next page refresh will re-resolve.
                        this.startingSlotMap = result.slot_assignments
                            ?? this.previewSlotMap
                            ?? {};
                    }
                }
                // Pending-state reset includes the preview map.
                this.previewSlotMap = null;
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

                // Filter server events up to current minute. Atmosphere events
                // (shots/fouls) beyond this minute are discarded and regenerated
                // below so they reflect substitutions and tactical changes.
                if (isET) {
                    this.extraTimeEvents = this.extraTimeEvents.filter(e => e.minute <= minute);
                } else {
                    this.events = this.events.filter(e => e.minute <= minute);
                    // Remove contextual narratives — they'll be freshly regenerated
                    // below to reflect the post-resimulation score.
                    this.events = this.events.filter(e => e.type !== 'contextual');
                }
                this.revealedEvents = this.revealedEvents.filter(e => e.minute <= minute && e.type !== 'contextual');

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
                        this.extraTimeEvents.push(...subEvents);
                    } else {
                        this.events.push(...subEvents);
                    }
                }

                // Regenerate atmosphere events (shots/fouls) for the remaining
                // match period, now aware of substitutions.
                const atmCfg = this._atmosphereConfig();
                const atmMaxMinute = isET ? 120 : 90;
                const freshAtmosphere = generateAtmosphereForPeriod({
                    ...atmCfg,
                    allEvents: isET ? [...this.events, ...this.extraTimeEvents] : this.events,
                    minMinute: minute + 1,
                    maxMinute: atmMaxMinute,
                });
                if (freshAtmosphere.length) {
                    if (isET) {
                        this.extraTimeEvents.push(...freshAtmosphere);
                        this.extraTimeEvents.sort((a, b) => a.minute - b.minute);
                    } else {
                        this.events.push(...freshAtmosphere);
                        this.events.sort((a, b) => a.minute - b.minute);
                    }
                }

                // Append new events and update scores
                if (isET) {
                    if (result.newEvents && result.newEvents.length > 0) {
                        this.extraTimeEvents.push(...result.newEvents);
                        this.extraTimeEvents.sort((a, b) => a.minute - b.minute);
                    }

                    addGoalNarratives(this.extraTimeEvents, this._atmosphereConfig());

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

                    this.finalHomeScore = result.newScore.home;
                    this.finalAwayScore = result.newScore.away;

                    this.events = this.synthesizeGoalsIfNeeded(this.events);

                    // Regenerate narratives: goal text for new server goals + contextual
                    // commentary for checkpoints after the tactical minute (old ones were
                    // removed because they reflected the pre-resimulation score).
                    const cfg = this._atmosphereConfig();
                    addGoalNarratives(this.events, cfg);
                    const freshContextual = generateContextualNarratives({ ...cfg, allEvents: this.events });
                    if (freshContextual.length) {
                        this.events = [...this.events, ...freshContextual].sort((a, b) => a.minute - b.minute);
                    }

                    // Recalculate after all event modifications (synthesize, narratives)
                    // to avoid stale indices from array insertions and re-sorts.
                    this.lastRevealedIndex = -1;
                    for (let i = 0; i < this.events.length; i++) {
                        if (this.events[i].minute <= this.currentMinute) {
                            this.lastRevealedIndex = i;
                        } else {
                            break;
                        }
                    }
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

                // Update player performances and recalculate ratings
                if (result.playerPerformances) {
                    updateRosterPerformances(this.homeLineupRoster, this.awayLineupRoster, result.playerPerformances);
                    this.recalculatePlayerRatings();
                }

                // Update MVP after resimulation
                if (result.mvpPlayerName !== undefined) {
                    this.mvpPlayerName = result.mvpPlayerName;
                    this.mvpPlayerTeamId = result.mvpPlayerTeamId;
                }

                // Close the panel and resume
                this.closeTacticalPanel();
            } catch (err) {
                console.error('Tactical actions request failed:', err);
                this.tacticalError = this.translations.tacticalErrorGeneric
                    || 'Something went wrong. Please try again.';
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
        // Player ratings
        // =============================

        recalculatePlayerRatings() {
            const allEvents = [...this.events, ...(this.extraTimeEvents || [])];
            const subMap = buildSubstitutionMap(allEvents);

            // Build sub-in player list for rating calculation
            // User bench players who entered have performance data
            const subsIn = [];
            for (const bp of this.benchPlayers) {
                if (bp.performance != null && subMap.subbedIn[bp.id]) {
                    subsIn.push({
                        id: bp.id,
                        performance: bp.performance,
                        positionGroup: bp.positionGroup,
                        teamId: this.userTeamId,
                    });
                }
            }
            // Opponent subs: check if they have performance in the roster cache
            for (const [inId, sub] of Object.entries(subMap.subbedIn)) {
                if (sub.teamId && sub.teamId !== this.userTeamId) {
                    // Find performance from the cached performances (passed via roster update)
                    const opponentRoster = this.homeTeamId === this.userTeamId
                        ? this.awayLineupRoster : this.homeLineupRoster;
                    // Opponent subs aren't in the roster, but may have performance from resim
                    // We can't rate them without performance data
                }
            }

            this.playerRatings = calculatePlayerRatings(
                this.homeLineupRoster,
                this.awayLineupRoster,
                allEvents,
                this.finalHomeScore,
                this.finalAwayScore,
                this.homeTeamId,
                this.awayTeamId,
                subsIn,
            );
        },

        ratingColor(rating) {
            return _ratingColor(rating);
        },

        getEventIcons() {
            const allEvents = [...this.events, ...(this.extraTimeEvents || [])];
            return countEvents(allEvents);
        },

        getSubMap() {
            const allEvents = [...this.events, ...(this.extraTimeEvents || [])];
            return buildSubstitutionMap(allEvents);
        },

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

        isAtmosphereEvent(event) {
            return event.type === 'shot_on_target' || event.type === 'shot_off_target' || event.type === 'foul' || event.type === 'contextual';
        },

        /**
         * Build the atmosphere config object from component state.
         * Used by both regular time and extra time generation.
         */
        _atmosphereConfig() {
            return {
                homeTeamId: this.homeTeamId,
                awayTeamId: this.awayTeamId,
                homeTeamName: this.homeTeamName,
                awayTeamName: this.awayTeamName,
                homePlayers: this.homeLineupRoster,
                awayPlayers: this.awayLineupRoster,
                homeScore: this.finalHomeScore,
                awayScore: this.finalAwayScore,
                venueName: this.venueName,
                homeArticle: this.homeArticle,
                awayArticle: this.awayArticle,
                narrativeTemplates: this.narrativeTemplates,
                userTeamId: this.userTeamId,
                tactics: {
                    userPlayingStyle: this.activePlayingStyle,
                    userPressing: this.activePressing,
                    userDefLine: this.activeDefLine,
                    userMentality: this.activeMentality,
                    opponentPlayingStyle: this.opponentPlayingStyle,
                    opponentPressing: this.opponentPressing,
                    opponentDefLine: this.opponentDefLine,
                    opponentMentality: this.opponentMentality,
                },
            };
        },

        /**
         * Generate atmosphere events and narratives for regular time,
         * merging them into the events array.
         */
        _injectAtmosphere() {
            const cfg = this._atmosphereConfig();
            addGoalNarratives(this.events, cfg);
            const atmosphere = generateRegularTimeAtmosphere({
                ...cfg,
                allEvents: this.events,
            });
            const tactical = generateTacticalNarratives({
                ...cfg,
                allEvents: this.events,
            });
            const allAtmosphere = [...atmosphere, ...tactical];
            if (allAtmosphere.length) {
                this.events = [...this.events, ...allAtmosphere].sort((a, b) => a.minute - b.minute);
            }
        },

        /**
         * Generate atmosphere events and narratives for extra time,
         * merging them into the extraTimeEvents array.
         * Called after ET events are loaded (fetch or preloaded).
         */
        _injectETAtmosphere() {
            const cfg = this._atmosphereConfig();
            addGoalNarratives(this.extraTimeEvents, cfg);
            // Include regular-time events for player availability checks
            const allEvents = [...this.events, ...this.extraTimeEvents];
            const atmosphere = generateExtraTimeAtmosphere({
                ...cfg,
                allEvents,
            });
            if (atmosphere.length) {
                this.extraTimeEvents = [...this.extraTimeEvents, ...atmosphere].sort((a, b) => a.minute - b.minute);
            }
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
         *
         * Normal case (current formation): start from the authoritative
         * starting-XI map and replay confirmed substitutions on top. No
         * algorithm, no reshuffling.
         *
         * Formation-preview case: the user is previewing a different shape
         * from the tactical panel and hasn't committed yet. Slot IDs change
         * between formations so the saved map is useless. We do a quick
         * local placement — walk the current on-pitch 11 and drop each into
         * the first empty slot of the new formation that matches their
         * primary position. It's not as smart as the backend algorithm,
         * but it's instant, and the user can drag-drop to fix anything
         * before committing. The real map is computed server-side when the
         * change is applied.
         */
        get slotAssignments() {
            const effectiveFormation = this.pendingFormation ?? this.activeFormation;
            const isFormationPreview = effectiveFormation !== this._pitchPositionsFormation;

            let map;
            if (isFormationPreview) {
                if (this.previewSlotMap) {
                    // Authoritative preview map from the backend — same
                    // algorithm the lineup page uses. Use it as-is.
                    map = { ...this.previewSlotMap };
                } else {
                    // Fetch in flight (or not started yet). Fall back to a
                    // naive local primary-match placement so the pitch isn't
                    // empty. The correct placement will snap in once
                    // refreshFormationPreview() resolves.
                    map = {};
                    const active = this.getActiveLineupPlayers();
                    for (const player of active) {
                        map = placeInFirstMatchingSlot(map, this.currentPitchSlots, player);
                    }
                }
            } else {
                // Start from the saved slot map and replay confirmed subs on top.
                map = { ...this.startingSlotMap };
                const confirmedSubs = this.substitutionsMade || [];
                for (const sub of confirmedSubs) {
                    map = applySubstitution(map, sub.playerOutId, sub.playerInId);
                }
            }

            // Layer pending subs on top (in-memory preview only — not committed).
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

            const pendingInIds = new Set();
            for (const sub of allPendingSubs) {
                map = applySubstitution(map, sub.playerOut.id, sub.playerIn.id);
                pendingInIds.add(sub.playerIn.id);
            }

            // Index players by id for the pitch view. Includes both the
            // starting XI and the full bench so subbed-in players render.
            const playersById = {};
            for (const p of this.lineupPlayers || []) playersById[p.id] = p;
            for (const p of this.benchPlayers || []) playersById[p.id] = p;

            const assignments = buildSlotView(map, this.currentPitchSlots, playersById, this.slotCompatibility);

            // Mark pending-sub players with isPendingSub so the pitch can
            // style them (dashed border, muted colors, etc.) without
            // committing the sub.
            for (const row of assignments) {
                if (row.player && pendingInIds.has(row.player.id)) {
                    row.player = { ...row.player, isPendingSub: true };
                }
            }

            // Post-process: ensure a non-red-carded Goalkeeper occupies the
            // GK slot. When a GK is sent off and a reserve GK comes on as a
            // direct swap, the reserve ends up in the GK slot automatically.
            // This block only matters in edge cases where the reserve GK was
            // subbed into a different slot (rare, old data paths).
            const redCarded = this.redCardedPlayerIds;
            const gkSlot = assignments.find(s => s.role === 'Goalkeeper');
            if (gkSlot?.player && redCarded.includes(gkSlot.player.id)) {
                const reserveGkSlot = assignments.find(s =>
                    s !== gkSlot &&
                    s.player &&
                    s.player.position === 'Goalkeeper' &&
                    !redCarded.includes(s.player.id)
                );
                if (reserveGkSlot) {
                    const temp = gkSlot.player;
                    gkSlot.player = reserveGkSlot.player;
                    reserveGkSlot.player = temp;
                    gkSlot.compatibility = getPlayerCompatibility(gkSlot.player, gkSlot.label, this.slotCompatibility);
                    reserveGkSlot.compatibility = getPlayerCompatibility(reserveGkSlot.player, reserveGkSlot.label, this.slotCompatibility);
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
            return this.groupSubstitutions(this.revealedEvents.filter(e => e.minute > 45 && e.minute <= 90));
        },

        get firstHalfEvents() {
            return this.groupSubstitutions(this.revealedEvents.filter(e => e.minute <= 45));
        },

        get showHalfTimeSeparator() {
            return this.phase === 'half_time' || this.phase === 'second_half' || this.phase === 'full_time'
                || this.isInExtraTime || this.phase === 'going_to_extra_time' || this.phase === 'penalties';
        },

        groupSubstitutions(events) {
            const result = [];
            for (const event of events) {
                if (event.type === 'substitution') {
                    const prev = result[result.length - 1];
                    if (prev && prev.type === 'substitution_group' && prev.minute === event.minute && prev.teamId === event.teamId) {
                        prev.substitutions.push({ playerInName: event.playerInName, playerName: event.playerName });
                        continue;
                    }
                    const prevSingle = result[result.length - 1];
                    if (prevSingle && prevSingle.type === 'substitution' && prevSingle.minute === event.minute && prevSingle.teamId === event.teamId) {
                        result[result.length - 1] = {
                            type: 'substitution_group',
                            minute: prevSingle.minute,
                            teamId: prevSingle.teamId,
                            substitutions: [
                                { playerInName: prevSingle.playerInName, playerName: prevSingle.playerName },
                                { playerInName: event.playerInName, playerName: event.playerName },
                            ],
                        };
                        continue;
                    }
                }
                result.push(event);
            }
            return result;
        },

        getTimelineMarkers() {
            const total = this.totalMinutes;
            return this.revealedEvents
                .filter(e => e.type !== 'assist')
                .map((e, index) => ({
                    position: Math.min((e.minute / total) * 100, 100),
                    type: e.type,
                    minute: e.minute,
                    index,
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
            if (this._onVisibilityChange) {
                document.removeEventListener('visibilitychange', this._onVisibilityChange);
            }
        },
    };

    // Mix module members (including getters) into the raw component object
    // BEFORE Alpine wraps it, so Alpine's reactivity sees them natively.
    mixinModule(component, subs);
    mixinModule(component, penalties);
    mixinModule(component, sim);

    return component;
}
