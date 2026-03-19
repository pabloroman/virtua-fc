import {
    getEffectivePosition as _getEffectivePosition,
    getInitials as _getInitials,
    getShirtStyle as _getShirtStyle,
    getNumberStyle as _getNumberStyle,
    getPlayerEnergy as _getPlayerEnergy,
    getEnergyColor,
    assignPlayersToSlots,
    getEventCoords as _getEventCoords,
    getDragPosition,
    getCellFromClientCoords,
    isValidGridCell as _isValidGridCell,
    getZoneColorClass as _getZoneColorClass,
} from './modules/pitch-renderer.js';

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
        manualAssignments: config.manualAssignments || {},
        _savedPitchPositions: config.pitchPositions ? { ...config.pitchPositions } : {},
        _positionJustApplied: false,
        _dragStartCoords: null,
        _wasDragging: false,
        _livePitchEl: null,

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
        _penaltyRevealTimer: null,

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

        // Animation loop
        _lastTick: null,
        _animFrame: null,
        _kickoffTimeout: null,
        _startETTimeout: null,

        // Speed presets: match minutes per real second
        speedRates: {
            1: 1.5,   // ~60s for full match
            2: 3.0,   // ~30s for full match
            4: 6.0,   // ~15s for full match
        },

        init() {
            // Bind drag handlers for pitch repositioning
            this._boundDragMove = (e) => this._onDragMove(e);
            this._boundDragEnd = (e) => this._onDragEnd(e);

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

            // When no match events exist but there's a final score (e.g. opponent
            // has no squad), generate synthetic goal events so the live simulation
            // reveals goals progressively instead of jumping to the final score.
            this.events = this.synthesizeGoalsIfNeeded(this.events);

            // Brief delay before kickoff
            this._kickoffTimeout = setTimeout(() => {
                this._kickoffTimeout = null;
                this.phase = 'first_half';
                this._lastTick = performance.now();
                this._animFrame = requestAnimationFrame(this.tick.bind(this));
            }, 1000);
        },

        tick(now) {
            if (this.phase === 'full_time' || this.phase === 'pre_match'
                || this.phase === 'going_to_extra_time' || this.phase === 'penalties') {
                return;
            }

            if (this.isPaused || this.userPaused || this.tacticalPanelOpen || this.penaltyPickerOpen) {
                this._lastTick = now;
                this._animFrame = requestAnimationFrame(this.tick.bind(this));
                return;
            }

            const deltaMs = now - this._lastTick;
            this._lastTick = now;

            const rate = this.speedRates[this.speed] || 1.5;
            const deltaMinutes = (deltaMs / 1000) * rate;

            const isExtraTime = this.phase === 'extra_time_first_half' || this.phase === 'extra_time_second_half';

            if (isExtraTime) {
                this.currentMinute = Math.min(this.currentMinute + deltaMinutes, 123);
            } else {
                this.currentMinute = Math.min(this.currentMinute + deltaMinutes, 93);
            }

            // Reveal events
            if (isExtraTime) {
                this.processETEvents();
            } else {
                this.processEvents();
            }

            // Update other match tickers
            this.updateOtherMatches();

            // Fluctuate possession display
            this.updatePossession();

            // Check for half-time
            if (this.phase === 'first_half' && this.currentMinute >= 45) {
                this.enterHalfTime();
                return;
            }

            // Check for end of regular time
            if (this.phase === 'second_half' && this.currentMinute >= 93) {
                this.enterRegularTimeEnd();
                return;
            }

            // Check for ET half-time
            if (this.phase === 'extra_time_first_half' && this.currentMinute >= 105) {
                this.enterETHalfTime();
                return;
            }

            // Check for end of extra time
            if (this.phase === 'extra_time_second_half' && this.currentMinute >= 123) {
                this.enterExtraTimeEnd();
                return;
            }

            this._animFrame = requestAnimationFrame(this.tick.bind(this));
        },

        synthesizeGoalsIfNeeded(events) {
            // Count goals already present in events
            let existingHomeGoals = 0;
            let existingAwayGoals = 0;
            for (const e of events) {
                if (e.type === 'goal') {
                    if (e.teamId === this.homeTeamId) existingHomeGoals++;
                    else existingAwayGoals++;
                } else if (e.type === 'own_goal') {
                    if (e.teamId === this.awayTeamId) existingHomeGoals++;
                    else existingAwayGoals++;
                }
            }

            const missingHome = this.finalHomeScore - existingHomeGoals;
            const missingAway = this.finalAwayScore - existingAwayGoals;

            if (missingHome <= 0 && missingAway <= 0) {
                return events;
            }

            // Generate synthetic goals spread across the match
            const synthetic = [];
            const totalMissing = Math.max(0, missingHome) + Math.max(0, missingAway);
            // Distribute goals between minute 8 and 88 with some randomness
            const slotSize = 80 / (totalMissing + 1);

            let slot = 0;
            for (let i = 0; i < Math.max(0, missingHome); i++) {
                slot++;
                const minute = Math.round(8 + slotSize * slot + (Math.random() * slotSize * 0.4 - slotSize * 0.2));
                synthetic.push({
                    minute: Math.max(1, Math.min(90, minute)),
                    type: 'goal',
                    playerName: this.homeTeamName,
                    teamId: this.homeTeamId,
                    gamePlayerId: null,
                    metadata: {},
                });
            }
            for (let i = 0; i < Math.max(0, missingAway); i++) {
                slot++;
                const minute = Math.round(8 + slotSize * slot + (Math.random() * slotSize * 0.4 - slotSize * 0.2));
                synthetic.push({
                    minute: Math.max(1, Math.min(90, minute)),
                    type: 'goal',
                    playerName: this.awayTeamName,
                    teamId: this.awayTeamId,
                    gamePlayerId: null,
                    metadata: {},
                });
            }

            // Merge with existing events and sort by minute
            return [...events, ...synthetic].sort((a, b) => a.minute - b.minute);
        },

        processEvents() {
            for (let i = this.lastRevealedIndex + 1; i < this.events.length; i++) {
                const event = this.events[i];
                if (event.minute <= this.currentMinute) {
                    this.revealEvent(event, i);
                } else {
                    break;
                }
            }
        },

        processETEvents() {
            for (let i = this.lastRevealedETIndex + 1; i < this.extraTimeEvents.length; i++) {
                const event = this.extraTimeEvents[i];
                if (event.minute <= this.currentMinute) {
                    this.revealETEvent(event, i);
                } else {
                    break;
                }
            }
        },

        revealEvent(event, index) {
            this.lastRevealedIndex = index;
            this.revealedEvents.unshift(event); // newest first
            this.latestEvent = event;

            if (event.type === 'goal' || event.type === 'own_goal') {
                this.updateScore(event);
                this.triggerGoalFlash();
                this.pauseForDrama(1500);
            }

            // Auto-open tactical panel on substitutions tab when user's player gets injured
            if (event.type === 'injury' && event.teamId === this.userTeamId && this.canSubstitute && this.hasWindowsLeft) {
                this.injuryAlertPlayer = event.playerName;
                this.openTacticalPanel('substitutions', true);
                // Pre-select the injured player as "player out"
                const injured = this.availableLineupForPicker.find(p => p.id === event.gamePlayerId);
                if (injured) {
                    this.selectedPlayerOut = injured;
                }
            }
        },

        revealETEvent(event, index) {
            this.lastRevealedETIndex = index;
            this.revealedEvents.unshift(event);
            this.latestEvent = event;

            if (event.type === 'goal' || event.type === 'own_goal') {
                this.updateScore(event);
                this.triggerGoalFlash();
                this.pauseForDrama(1500);
            }
        },

        updateScore(event) {
            const isHomeGoal =
                (event.type === 'goal' && event.teamId === this.homeTeamId) ||
                (event.type === 'own_goal' && event.teamId === this.awayTeamId);

            if (isHomeGoal) {
                this.homeScore++;
            } else {
                this.awayScore++;
            }
        },

        triggerGoalFlash() {
            this.goalFlash = true;
            setTimeout(() => {
                this.goalFlash = false;
            }, 800);
        },

        pauseForDrama(ms) {
            this.isPaused = true;
            clearTimeout(this.pauseTimer);
            this.pauseTimer = setTimeout(() => {
                this.isPaused = false;
            }, ms);
        },

        enterHalfTime() {
            this.currentMinute = 45;
            this.phase = 'half_time';

            // Auto-resume after a pause
            setTimeout(() => {
                this.phase = 'second_half';
                this._lastTick = performance.now();
                this._animFrame = requestAnimationFrame(this.tick.bind(this));
            }, 1500);
        },

        enterRegularTimeEnd() {
            this.currentMinute = 90;

            // Reveal any remaining regular time events
            for (let i = this.lastRevealedIndex + 1; i < this.events.length; i++) {
                const event = this.events[i];
                this.lastRevealedIndex = i;
                this.revealedEvents.unshift(event);
                if (event.type === 'goal' || event.type === 'own_goal') {
                    this.updateScore(event);
                }
            }

            // Ensure regular time scores match
            this.homeScore = this.finalHomeScore;
            this.awayScore = this.finalAwayScore;

            // Check if this is a knockout match and we need extra time
            if (this.isKnockout && this.needsExtraTime()) {
                // If ET data was preloaded (page refresh), use it directly
                if (this.preloadedExtraTimeData) {
                    this.phase = 'going_to_extra_time';
                    this._startETTimeout = setTimeout(() => this.startExtraTime(), 2000);
                } else {
                    this.phase = 'going_to_extra_time';
                    this.fetchExtraTime();
                }
            } else {
                this.enterFullTime();
            }
        },

        needsExtraTime() {
            if (this.twoLeggedInfo) {
                // Two-legged tie: check aggregate
                const firstLegHome = this.twoLeggedInfo.firstLegHomeScore;
                const firstLegAway = this.twoLeggedInfo.firstLegAwayScore;
                // In the second leg, the tie's home team plays away
                // match.home = tie.away, match.away = tie.home
                const tieHomeTotal = firstLegHome + this.finalAwayScore;
                const tieAwayTotal = firstLegAway + this.finalHomeScore;
                return tieHomeTotal === tieAwayTotal;
            }

            // Single leg: check if tied
            return this.finalHomeScore === this.finalAwayScore;
        },

        async fetchExtraTime() {
            this.extraTimeLoading = true;

            try {
                const response = await fetch(this.extraTimeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({}),
                });

                if (!response.ok) {
                    console.error('Extra time request failed');
                    this.enterFullTime();
                    return;
                }

                const result = await response.json();

                if (!result.needed) {
                    this.enterFullTime();
                    return;
                }

                this.extraTimeEvents = result.extraTimeEvents || [];
                this.etHomeScore = result.homeScoreET || 0;
                this.etAwayScore = result.awayScoreET || 0;
                this._needsPenalties = result.needsPenalties || false;

                if (result.homePossession !== undefined) {
                    this._basePossession = result.homePossession;
                    this._possessionDisplay = result.homePossession;
                    this.homePossession = result.homePossession;
                    this.awayPossession = result.awayPossession;
                    this.resetPossessionTarget();
                }

                // Brief pause showing "Extra Time" before starting
                this._startETTimeout = setTimeout(() => this.startExtraTime(), 2000);
            } catch (err) {
                console.error('Extra time request failed:', err);
                this.enterFullTime();
            } finally {
                this.extraTimeLoading = false;
            }
        },

        startExtraTime() {
            this.currentMinute = 91;
            this.phase = 'extra_time_first_half';
            this.lastRevealedETIndex = -1;
            this._lastTick = performance.now();
            this._animFrame = requestAnimationFrame(this.tick.bind(this));
        },

        enterETHalfTime() {
            this.currentMinute = 105;
            this.phase = 'extra_time_half_time';

            setTimeout(() => {
                this.phase = 'extra_time_second_half';
                this._lastTick = performance.now();
                this._animFrame = requestAnimationFrame(this.tick.bind(this));
            }, 1500);
        },

        enterExtraTimeEnd() {
            clearTimeout(this._startETTimeout);
            this.currentMinute = 120;

            // Reveal any remaining ET events
            for (let i = this.lastRevealedETIndex + 1; i < this.extraTimeEvents.length; i++) {
                const event = this.extraTimeEvents[i];
                this.lastRevealedETIndex = i;
                this.revealedEvents.unshift(event);
                if (event.type === 'goal' || event.type === 'own_goal') {
                    this.updateScore(event);
                }
            }

            // Ensure ET scores match
            this.homeScore = this.finalHomeScore + this.etHomeScore;
            this.awayScore = this.finalAwayScore + this.etAwayScore;

            if (this._needsPenalties) {
                this.phase = 'penalties';
                // Open penalty picker for user to select kickers
                this.openPenaltyPicker();
            } else if (this.penaltyResult) {
                // Preloaded penalties (page refresh after they were resolved)
                this.phase = 'penalties';
                setTimeout(() => this.enterFullTime(), 3000);
            } else {
                this.enterFullTime();
            }
        },

        enterFullTime() {
            this.phase = 'full_time';

            if (!this.hasExtraTime) {
                this.currentMinute = 90;
                // Ensure final scores match for regular time
                this.homeScore = this.finalHomeScore;
                this.awayScore = this.finalAwayScore;
                // Reveal any remaining events
                for (let i = this.lastRevealedIndex + 1; i < this.events.length; i++) {
                    this.revealedEvents.unshift(this.events[i]);
                }
                this.lastRevealedIndex = this.events.length - 1;
            } else {
                this.currentMinute = 120;
                // Scores were already set in enterExtraTimeEnd or skipExtraTime
            }

            if (this._animFrame) {
                cancelAnimationFrame(this._animFrame);
            }
        },

        updateOtherMatches() {
            for (let i = 0; i < this.otherMatches.length; i++) {
                const match = this.otherMatches[i];
                let home = 0;
                let away = 0;
                for (const goal of match.goalMinutes) {
                    if (goal.minute <= this.currentMinute) {
                        if (goal.side === 'home') home++;
                        else away++;
                    }
                }
                this.otherMatchScores[i] = { homeScore: home, awayScore: away };
            }
        },

        updatePossession() {
            // Every 2-4 match-minutes, pick a new random target around the base
            if (this.currentMinute >= this._possessionNextShift) {
                const swing = (Math.random() - 0.5) * 16; // ±8
                this._possessionTarget = Math.max(25, Math.min(75, this._basePossession + swing));
                this._possessionNextShift = this.currentMinute + 2 + Math.random() * 2;
            }
            // Lerp displayed value toward target (smooth transition)
            this._possessionDisplay += (this._possessionTarget - this._possessionDisplay) * 0.03;
            const rounded = Math.round(this._possessionDisplay);
            if (rounded !== this.homePossession) {
                this.homePossession = rounded;
                this.awayPossession = 100 - rounded;
            }
        },

        resetPossessionTarget() {
            this._possessionTarget = this._basePossession;
            this._possessionNextShift = this.currentMinute + 1 + Math.random() * 2;
        },

        togglePause() {
            this.userPaused = !this.userPaused;
        },

        // Speed controls
        setSpeed(s) {
            this.speed = s;
            localStorage.setItem('liveMatchSpeed', s);
        },

        skipToEnd() {
            this.userPaused = false;

            // Cancel the kickoff timeout if skip is pressed during pre_match
            if (this._kickoffTimeout) {
                clearTimeout(this._kickoffTimeout);
                this._kickoffTimeout = null;
            }

            // If penalties are being animated, fast-forward the reveal
            if (this.phase === 'penalties' && this.penaltyKicks.length > 0
                && this.revealedPenaltyKicks.length < this.penaltyKicks.length) {
                clearTimeout(this._penaltyRevealTimer);
                this.revealedPenaltyKicks = [...this.penaltyKicks];
                setTimeout(() => this.enterFullTime(), 500);
                return;
            }

            if (this.isKnockout && !this.hasExtraTime && !this._skippingToEnd) {
                // For knockout matches, first skip to end of regular time
                // which will trigger ET check
                this._skippingToEnd = true;
                this.currentMinute = 93;
                this.updateOtherMatches();
                this.enterRegularTimeEnd();

                // If ET was triggered, wait for it and then skip through it
                if (this.phase === 'going_to_extra_time') {
                    const waitForET = () => {
                        if (this.extraTimeEvents.length > 0 || this._needsPenalties || this.etHomeScore > 0 || this.etAwayScore > 0) {
                            this.skipExtraTime();
                        } else if (this.phase === 'going_to_extra_time') {
                            setTimeout(waitForET, 100);
                        }
                        // If phase changed to full_time (no ET needed), we're done
                    };
                    waitForET();
                }
                return;
            }

            if (this.hasExtraTime && this.phase === 'going_to_extra_time') {
                clearTimeout(this._startETTimeout);
                this.skipExtraTime();
                return;
            }

            if (this.hasExtraTime && (this.phase === 'extra_time_first_half'
                || this.phase === 'extra_time_second_half' || this.phase === 'extra_time_half_time')) {
                this.skipExtraTime();
                return;
            }

            this.currentMinute = 93;
            this.updateOtherMatches();
            this.enterFullTime();
        },

        skipExtraTime() {
            clearTimeout(this._startETTimeout);
            this._skippingToEnd = false;
            this.currentMinute = 123;

            // Reveal all ET events
            for (let i = this.lastRevealedETIndex + 1; i < this.extraTimeEvents.length; i++) {
                const event = this.extraTimeEvents[i];
                this.lastRevealedETIndex = i;
                this.revealedEvents.unshift(event);
                if (event.type === 'goal' || event.type === 'own_goal') {
                    this.updateScore(event);
                }
            }

            // Ensure ET scores match
            this.homeScore = this.finalHomeScore + this.etHomeScore;
            this.awayScore = this.finalAwayScore + this.etAwayScore;

            if (this._needsPenalties) {
                this.phase = 'penalties';
                this.openPenaltyPicker();
            } else if (this.penaltyResult) {
                this.phase = 'penalties';
                setTimeout(() => this.enterFullTime(), 2000);
            } else {
                this.enterFullTime();
            }
        },

        // =============================
        // Penalty picker & shootout
        // =============================

        openPenaltyPicker() {
            this.selectedPenaltyKickers = [];
            this.penaltyPickerOpen = true;
            document.body.classList.add('overflow-y-hidden');
        },

        get availablePenaltyPlayers() {
            const selectedIds = this.selectedPenaltyKickers.map(k => k.id);
            const confirmedOutIds = this.substitutionsMade.map(s => s.playerOutId);
            const confirmedInIds = this.substitutionsMade.map(s => s.playerInId);
            const redCarded = this.redCardedPlayerIds;

            // Original lineup players still on pitch
            const onPitch = this.lineupPlayers.filter(p =>
                !confirmedOutIds.includes(p.id) && !redCarded.includes(p.id) && !selectedIds.includes(p.id)
            );
            // Players who came on via substitution
            const subsOnPitch = this.benchPlayers.filter(p =>
                confirmedInIds.includes(p.id) && !confirmedOutIds.includes(p.id)
                && !redCarded.includes(p.id) && !selectedIds.includes(p.id)
            );

            return [...onPitch, ...subsOnPitch]
                .sort((a, b) => (b.technicalAbility ?? 50) - (a.technicalAbility ?? 50));
        },

        addPenaltyKicker(player) {
            if (this.selectedPenaltyKickers.length >= 5) return;
            this.selectedPenaltyKickers.push({ ...player });
        },

        removePenaltyKicker(index) {
            this.selectedPenaltyKickers.splice(index, 1);
        },

        async confirmPenaltyKickers() {
            if (this.selectedPenaltyKickers.length < 5 || this.penaltyProcessing) return;
            this.penaltyProcessing = true;

            const kickerOrder = this.selectedPenaltyKickers.map(k => k.id);

            try {
                const response = await fetch(this.penaltiesUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ kickerOrder }),
                });

                if (!response.ok) {
                    console.error('Penalty request failed');
                    this.penaltyProcessing = false;
                    return;
                }

                const result = await response.json();

                this.penaltyResult = {
                    home: result.homeScore,
                    away: result.awayScore,
                };
                this.penaltyKicks = result.kicks || [];
                this._needsPenalties = false;

                // Close picker and start kick-by-kick reveal
                this.penaltyPickerOpen = false;
                document.body.classList.remove('overflow-y-hidden');
                this.revealPenaltyKicks();
            } catch (err) {
                console.error('Penalty request failed:', err);
            } finally {
                this.penaltyProcessing = false;
            }
        },

        revealPenaltyKicks() {
            this.revealedPenaltyKicks = [];
            let idx = 0;

            const revealNext = () => {
                if (idx >= this.penaltyKicks.length) {
                    // All kicks revealed, transition to full time
                    setTimeout(() => this.enterFullTime(), 2000);
                    return;
                }

                this.revealedPenaltyKicks.push(this.penaltyKicks[idx]);
                idx++;

                // Pause between kicks for drama
                this._penaltyRevealTimer = setTimeout(revealNext, 800);
            };

            // Start revealing after a short pause
            this._penaltyRevealTimer = setTimeout(revealNext, 500);
        },

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

        get penaltyHomeScore() {
            if (this.penaltyKicks.length > 0) {
                return this.revealedPenaltyKicks.filter(k => k.side === 'home' && k.scored).length;
            }
            return this.penaltyResult ? this.penaltyResult.home : 0;
        },

        get penaltyAwayScore() {
            if (this.penaltyKicks.length > 0) {
                return this.revealedPenaltyKicks.filter(k => k.side === 'away' && k.scored).length;
            }
            return this.penaltyResult ? this.penaltyResult.away : 0;
        },

        get penaltyWinner() {
            if (!this.penaltyResult) return null;
            const homeWon = this.penaltyResult.home > this.penaltyResult.away;
            return {
                name: homeWon ? this.homeTeamName : this.awayTeamName,
                image: homeWon ? this.homeTeamImage : this.awayTeamImage,
            };
        },

        // =============================
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
                        this.livePitchPositions = {};
                        this._savedPitchPositions = {};
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
        // Substitution methods
        // =============================

        get redCardedPlayerIds() {
            return this.revealedEvents
                .filter(e => e.type === 'red_card' && e.teamId === this.userTeamId)
                .map(e => e.gamePlayerId);
        },

        get yellowCardedPlayerIds() {
            return this.revealedEvents
                .filter(e => e.type === 'yellow_card' && e.teamId === this.userTeamId)
                .map(e => e.gamePlayerId);
        },

        isPlayerYellowCarded(playerId) {
            return this.yellowCardedPlayerIds.includes(playerId);
        },

        // Dynamic limits: 6 subs / 4 windows during ET in knockout, 5/3 otherwise
        get effectiveMaxSubstitutions() {
            return (this.isKnockout && this.hasExtraTime) ? 6 : this.maxSubstitutions;
        },

        get effectiveMaxWindows() {
            return (this.isKnockout && this.hasExtraTime) ? 4 : this.maxWindows;
        },

        get windowsUsed() {
            // Count unique minutes in substitutionsMade — each unique minute = one window
            const minutes = new Set(this.substitutionsMade.map(s => s.minute));
            return minutes.size;
        },

        get hasWindowsLeft() {
            return this.windowsUsed < this.effectiveMaxWindows;
        },

        get subsRemaining() {
            return this.effectiveMaxSubstitutions - this.substitutionsMade.length - this.pendingSubs.length;
        },

        get canSubstitute() {
            return this.substitutionsMade.length + this.pendingSubs.length < this.effectiveMaxSubstitutions;
        },

        get canAddMoreToPending() {
            return this.canSubstitute && this.pendingSubs.length < this.subsRemaining;
        },

        // Lineup players considering both confirmed subs AND pending subs in this window
        get availableLineupForPicker() {
            const confirmedOutIds = this.substitutionsMade.map(s => s.playerOutId);
            const confirmedInIds = this.substitutionsMade.map(s => s.playerInId);
            const pendingOutIds = this.pendingSubs.map(s => s.playerOut.id);
            const pendingInIds = this.pendingSubs.map(s => s.playerIn.id);
            const allOutIds = [...confirmedOutIds, ...pendingOutIds];
            const allInIds = [...confirmedInIds, ...pendingInIds];
            const redCarded = this.redCardedPlayerIds;

            // Original lineup players still on pitch
            const onPitch = this.lineupPlayers.filter(p =>
                !allOutIds.includes(p.id) && !redCarded.includes(p.id)
            );

            // Players who came on (confirmed or pending) and are still on pitch
            const subsOnPitch = this.benchPlayers.filter(p =>
                allInIds.includes(p.id) && !allOutIds.includes(p.id) && !redCarded.includes(p.id)
            );

            return [...onPitch, ...subsOnPitch].sort((a, b) => a.positionSort - b.positionSort);
        },

        // Bench players minus those already subbed in (confirmed or pending)
        get availableBenchForPicker() {
            const confirmedInIds = this.substitutionsMade.map(s => s.playerInId);
            const pendingInIds = this.pendingSubs.map(s => s.playerIn.id);
            const allInIds = [...confirmedInIds, ...pendingInIds];
            return this.benchPlayers.filter(p => !allInIds.includes(p.id)).sort((a, b) => a.positionSort - b.positionSort);
        },

        // Keep old getters for the tactical bar display (confirmed subs only)
        get availableLineupPlayers() {
            return this.availableLineupForPicker;
        },

        get availableBenchPlayers() {
            return this.availableBenchForPicker;
        },

        resetSubstitutions() {
            this.selectedPlayerOut = null;
            this.selectedPlayerIn = null;
            this.livePitchSelectedOutId = null;
            this.pendingSubs = [];
        },

        addPendingSub() {
            if (!this.selectedPlayerOut || !this.selectedPlayerIn) return;
            this.pendingSubs.push({
                playerOut: { ...this.selectedPlayerOut },
                playerIn: { ...this.selectedPlayerIn },
            });
            this.selectedPlayerOut = null;
            this.selectedPlayerIn = null;
        },

        removePendingSub(index) {
            this.pendingSubs.splice(index, 1);
        },


        recalculateScore() {
            let home = 0;
            let away = 0;
            for (const event of this.revealedEvents) {
                if (event.type === 'goal') {
                    if (event.teamId === this.homeTeamId) home++;
                    else away++;
                } else if (event.type === 'own_goal') {
                    if (event.teamId === this.homeTeamId) away++;
                    else home++;
                }
            }
            this.homeScore = home;
            this.awayScore = away;
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

            const assignments = assignPlayersToSlots(slots, activeLineup, this.slotCompatibility, this.manualAssignments);

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

        /**
         * Get the current active lineup players (starting XI with substitutions applied).
         */
        getActiveLineupPlayers() {
            const subbedOutIds = new Set(this.substitutionsMade.map(s => s.playerOutId));
            const subbedInIds = new Set(this.substitutionsMade.map(s => s.playerInId));

            // Filter starting lineup: remove subbed out players
            const remaining = this.lineupPlayers.filter(p => !subbedOutIds.has(p.id));

            // Add subbed-in players from bench
            const subbedIn = this.benchPlayers.filter(p => subbedInIds.has(p.id));

            return [...remaining, ...subbedIn];
        },

        getEffectivePosition(slotId) {
            const gc = this.gridConfig;
            if (!gc) return null;
            return _getEffectivePosition(slotId, this.livePitchPositions, this.currentPitchSlots, gc.cols, gc.rows);
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
            const colors = {
                'Goalkeeper': 'bg-amber-500',
                'Defender': 'bg-blue-600',
                'Midfielder': 'bg-emerald-600',
                'Forward': 'bg-red-600',
            };
            return colors[group] || 'bg-emerald-600';
        },

        getOvrBadgeClasses(score) {
            if (score >= 80) return 'bg-emerald-500 text-white';
            if (score >= 70) return 'bg-lime-500 text-white';
            if (score >= 60) return 'bg-amber-500 text-white';
            return 'bg-slate-300 text-slate-700';
        },

        // =====================================================================
        // Pitch Drag & Repositioning (live match)
        // =====================================================================

        _getLivePitchElement() {
            if (!this._livePitchEl) {
                this._livePitchEl = document.getElementById('live-pitch-field');
            }
            return this._livePitchEl;
        },

        startDrag(slotId, event) {
            const slot = this.currentPitchSlots.find(s => s.id === slotId);
            if (slot && slot.role === 'Goalkeeper') return;

            event.preventDefault();

            // Record start coords for tap-vs-drag threshold
            const coords = _getEventCoords(event);
            this._dragStartCoords = { x: coords.clientX, y: coords.clientY };
            this._wasDragging = false;
            this._pendingDragSlotId = slotId;

            document.addEventListener('mousemove', this._boundDragMove);
            document.addEventListener('mouseup', this._boundDragEnd);
            document.addEventListener('touchmove', this._boundDragMove, { passive: false });
            document.addEventListener('touchend', this._boundDragEnd);
        },

        _onDragMove(event) {
            if (!this._pendingDragSlotId && this.draggingSlotId === null) return;
            event.preventDefault();

            const coords = _getEventCoords(event);

            // Check if we've exceeded the tap-vs-drag threshold (5px)
            if (this._pendingDragSlotId && !this.draggingSlotId) {
                const dx = coords.clientX - this._dragStartCoords.x;
                const dy = coords.clientY - this._dragStartCoords.y;
                if (Math.sqrt(dx * dx + dy * dy) < 5) return;

                // Threshold exceeded — activate drag
                this.draggingSlotId = this._pendingDragSlotId;
                this._pendingDragSlotId = null;
                this._wasDragging = true;
                this.positioningSlotId = null;
            }

            this.dragPosition = getDragPosition(coords.clientX, coords.clientY, this._getLivePitchElement());
        },

        _onDragEnd(event) {
            const coords = _getEventCoords(event);

            if (this.draggingSlotId !== null) {
                const cell = getCellFromClientCoords(coords.clientX, coords.clientY, this._getLivePitchElement(), this.gridConfig);
                if (cell) {
                    this.setSlotGridPosition(this.draggingSlotId, cell.col, cell.row);
                }
            }

            this.draggingSlotId = null;
            this.dragPosition = null;
            this._pendingDragSlotId = null;
            this._dragStartCoords = null;

            document.removeEventListener('mousemove', this._boundDragMove);
            document.removeEventListener('mouseup', this._boundDragEnd);
            document.removeEventListener('touchmove', this._boundDragMove);
            document.removeEventListener('touchend', this._boundDragEnd);
        },

        getSlotCell(slotId) {
            const customPos = this.livePitchPositions[String(slotId)];
            if (customPos) return { col: customPos[0], row: customPos[1] };
            const gc = this.gridConfig;
            if (!gc) return null;
            const formation = this.pendingFormation ?? this.activeFormation;
            const defaultCells = gc.defaultCells[formation];
            return defaultCells ? defaultCells[slotId] : null;
        },

        _findSlotAtCell(col, row, excludeSlotId) {
            const assignments = this.slotAssignments;
            for (const slot of assignments) {
                if (slot.id === excludeSlotId || !slot.player) continue;
                const cell = this.getSlotCell(slot.id);
                if (cell && cell.col === col && cell.row === row) return slot;
            }
            return null;
        },

        setSlotGridPosition(slotId, col, row) {
            const slot = this.currentPitchSlots.find(s => s.id === slotId);
            if (!slot) return;
            if (!_isValidGridCell(slot.label, col, row, this.gridConfig)) return;

            const occupying = this._findSlotAtCell(col, row, slotId);
            const newPositions = { ...this.livePitchPositions };

            if (occupying) {
                if (occupying.role === 'Goalkeeper') return;
                const draggedCell = this.getSlotCell(slotId);
                if (draggedCell) {
                    newPositions[String(occupying.id)] = [draggedCell.col, draggedCell.row];
                }
            }

            newPositions[String(slotId)] = [col, row];
            this.livePitchPositions = newPositions;
            this._savedPitchPositions = JSON.parse(JSON.stringify(newPositions));
            this._positionJustApplied = true;
            this.positioningSlotId = null;
        },

        selectForRepositioning(slotId) {
            const slot = this.currentPitchSlots.find(s => s.id === slotId);
            if (slot && slot.role === 'Goalkeeper') return;
            if (this.positioningSlotId === slotId) {
                this.positioningSlotId = null;
            } else {
                this.positioningSlotId = slotId;
            }
        },

        handleGridCellClick(col, row) {
            if (this.positioningSlotId === null) return;
            this.setSlotGridPosition(this.positioningSlotId, col, row);
        },

        isValidGridCell(slotLabel, col, row) {
            return _isValidGridCell(slotLabel, col, row, this.gridConfig);
        },

        getGridCellState(col, row) {
            if (this.positioningSlotId === null && this.draggingSlotId === null) return 'neutral';

            const activeSlotId = this.positioningSlotId ?? this.draggingSlotId;
            const slot = this.currentPitchSlots.find(s => s.id === activeSlotId);
            if (!slot) return 'neutral';

            if (!_isValidGridCell(slot.label, col, row, this.gridConfig)) return 'invalid';

            const occupying = this._findSlotAtCell(col, row, activeSlotId);
            if (occupying && occupying.role === 'Goalkeeper') return 'occupied';

            if (slot.role === 'Goalkeeper') return 'valid';

            if (row <= 4) return 'valid-def';
            if (row <= 9) return 'valid-mid';
            return 'valid-fwd';
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
        // Energy / Stamina
        // =============================

        calculateDrainRate(physicalAbility, age, positionGroup) {
            const baseDrain = 0.75;
            const physicalBonus = (physicalAbility - 50) * 0.005;
            const agePenalty = Math.max(0, (age - 28)) * 0.015;
            let drain = baseDrain - physicalBonus + agePenalty;
            if (positionGroup === 'Goalkeeper') drain *= 0.5;
            return Math.max(0, drain);
        },

        getPlayerEnergy(player) {
            if (player.minuteEntered === null || player.minuteEntered === undefined) return 100;
            const minutesPlayed = Math.max(0, Math.floor(this.currentMinute) - player.minuteEntered);
            const drain = this.calculateDrainRate(player.physicalAbility, player.age, player.positionGroup);
            return Math.max(0, Math.round(100 - drain * minutesPlayed));
        },

        getEnergyColor(energy) {
            if (energy > 60) return 'bg-emerald-500';
            if (energy > 30) return 'bg-amber-400';
            return 'bg-red-500';
        },

        getEnergyBarBg(energy) {
            if (energy > 60) return 'bg-emerald-500/20';
            if (energy > 30) return 'bg-amber-400/20';
            return 'bg-red-500/20';
        },

        getEnergyTextColor(energy) {
            if (energy > 60) return 'text-emerald-600';
            if (energy > 30) return 'text-amber-600';
            return 'text-red-600';
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
            if (this._animFrame) {
                cancelAnimationFrame(this._animFrame);
            }
            clearTimeout(this.pauseTimer);
            clearTimeout(this._kickoffTimeout);
            clearTimeout(this._startETTimeout);
            clearTimeout(this._penaltyRevealTimer);
            clearInterval(this._processingPollTimer);
            document.body.classList.remove('overflow-y-hidden');
        },
    };
}
