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
        substituteUrl: config.substituteUrl || '',
        csrfToken: config.csrfToken || '',
        maxSubstitutions: config.maxSubstitutions || 5,
        maxWindows: config.maxWindows || 3,

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
        tacticsUrl: config.tacticsUrl || '',
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

        // Tactical change state
        pendingFormation: null,
        pendingMentality: null,
        pendingPlayingStyle: null,
        pendingPressing: null,
        pendingDefLine: null,
        tacticsProcessing: false,

        // Clock state
        currentMinute: 0,
        speed: 1,
        // Phases: pre_match, first_half, half_time, second_half,
        //         going_to_extra_time, extra_time_first_half, extra_time_half_time,
        //         extra_time_second_half, penalties, full_time
        phase: 'pre_match',
        isPaused: false,
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
        subProcessing: false,
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

        // Animation loop
        _lastTick: null,
        _animFrame: null,
        _kickoffTimeout: null,
        _startETTimeout: null,

        // Speed presets: match minutes per real second
        speedRates: {
            1: 3.0,   // 30s for full match
            2: 6.0,   // 15s
        },

        init() {
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

            if (this.isPaused || this.tacticalPanelOpen || this.penaltyPickerOpen) {
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

        // Speed controls
        setSpeed(s) {
            this.speed = s;
        },

        skipToEnd() {
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
            this.pendingSubs = [];
            this.pendingFormation = null;
            this.pendingMentality = null;
            this.injuryAlertPlayer = null;
            document.body.classList.remove('overflow-y-hidden');
        },

        get hasPendingChanges() {
            return this.pendingSubs.length > 0
                || (this.selectedPlayerOut !== null && this.selectedPlayerIn !== null)
                || this.hasTacticalChanges;
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

        async confirmTacticalChanges() {
            if (!this.hasTacticalChanges || this.tacticsProcessing) return;
            this.tacticsProcessing = true;

            const minute = Math.floor(this.currentMinute);

            try {
                const response = await fetch(this.tacticsUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        minute,
                        formation: this.pendingFormation !== this.activeFormation ? this.pendingFormation : null,
                        mentality: this.pendingMentality !== this.activeMentality ? this.pendingMentality : null,
                        playing_style: this.pendingPlayingStyle !== this.activePlayingStyle ? this.pendingPlayingStyle : null,
                        pressing: this.pendingPressing !== this.activePressing ? this.pendingPressing : null,
                        defensive_line: this.pendingDefLine !== this.activeDefLine ? this.pendingDefLine : null,
                        previousSubstitutions: this.substitutionsMade.map(s => ({
                            playerOutId: s.playerOutId,
                            playerInId: s.playerInId,
                            minute: s.minute,
                        })),
                    }),
                });

                if (!response.ok) {
                    const error = await response.json();
                    console.error('Tactical change failed:', error);
                    this.tacticsProcessing = false;
                    return;
                }

                const result = await response.json();
                const isETChange = result.isExtraTime || false;

                // Update active tactics
                if (result.formation) {
                    this.activeFormation = result.formation;
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

                if (isETChange) {
                    // ET tactical change: update extra time events and scores
                    this.extraTimeEvents = this.extraTimeEvents.filter(e => e.minute <= minute);
                    this.revealedEvents = this.revealedEvents.filter(e => e.minute <= minute);

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

                    this.recalculateScore();
                } else {
                    // Regular time tactical change
                    this.events = this.events.filter(e => e.minute <= minute);
                    this.revealedEvents = this.revealedEvents.filter(e => e.minute <= minute);

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

                    this.recalculateScore();
                }

                // Close the panel and resume
                this.closeTacticalPanel();
            } catch (err) {
                console.error('Tactical change request failed:', err);
            } finally {
                this.tacticsProcessing = false;
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

        async confirmSubstitutions() {
            // If there's a selected pair not yet added to pending, add it first
            if (this.selectedPlayerOut && this.selectedPlayerIn) {
                this.addPendingSub();
            }

            if (this.pendingSubs.length === 0 || this.subProcessing) return;

            this.subProcessing = true;

            const subMinute = Math.floor(this.currentMinute);

            try {
                const response = await fetch(this.substituteUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        substitutions: this.pendingSubs.map(s => ({
                            playerOutId: s.playerOut.id,
                            playerInId: s.playerIn.id,
                        })),
                        minute: subMinute,
                        previousSubstitutions: this.substitutionsMade.map(s => ({
                            playerOutId: s.playerOutId,
                            playerInId: s.playerInId,
                            minute: s.minute,
                        })),
                    }),
                });

                if (!response.ok) {
                    const error = await response.json();
                    console.error('Substitution failed:', error);
                    this.subProcessing = false;
                    return;
                }

                const result = await response.json();
                const isETSub = result.isExtraTime || false;

                // Record all substitutions in the batch
                for (const sub of result.substitutions) {
                    this.substitutionsMade.push({
                        playerOutId: sub.playerOutId,
                        playerInId: sub.playerInId,
                        playerOutName: sub.playerOutName,
                        playerInName: sub.playerInName,
                        minute: subMinute,
                    });

                    // Set minuteEntered on the bench player who just came on
                    const benchPlayer = this.benchPlayers.find(p => p.id === sub.playerInId);
                    if (benchPlayer) {
                        benchPlayer.minuteEntered = subMinute;
                    }
                }

                if (isETSub) {
                    // ET substitution: update extra time events and scores
                    this.extraTimeEvents = this.extraTimeEvents.filter(e => e.minute <= subMinute);
                    this.revealedEvents = this.revealedEvents.filter(e => e.minute <= subMinute);

                    // Add substitution events to the feed
                    for (const sub of result.substitutions) {
                        this.revealedEvents.unshift({
                            minute: subMinute,
                            type: 'substitution',
                            playerName: sub.playerOutName,
                            playerInName: sub.playerInName,
                            teamId: sub.teamId,
                        });
                    }

                    // Append new ET events
                    if (result.newEvents && result.newEvents.length > 0) {
                        this.extraTimeEvents.push(...result.newEvents);
                        this.extraTimeEvents.sort((a, b) => a.minute - b.minute);
                    }

                    // Reset ET reveal index
                    this.lastRevealedETIndex = -1;
                    for (let i = 0; i < this.extraTimeEvents.length; i++) {
                        if (this.extraTimeEvents[i].minute <= this.currentMinute) {
                            this.lastRevealedETIndex = i;
                        } else {
                            break;
                        }
                    }

                    // Update ET scores
                    this.etHomeScore = result.newScore.home;
                    this.etAwayScore = result.newScore.away;
                    this._needsPenalties = result.needsPenalties || false;

                    // Recalculate displayed score
                    this.recalculateScore();
                } else {
                    // Regular time substitution
                    this.events = this.events.filter(e => e.minute <= subMinute);
                    this.revealedEvents = this.revealedEvents.filter(e => e.minute <= subMinute);

                    // Add substitution events to the feed
                    for (const sub of result.substitutions) {
                        this.revealedEvents.unshift({
                            minute: subMinute,
                            type: 'substitution',
                            playerName: sub.playerOutName,
                            playerInName: sub.playerInName,
                            teamId: sub.teamId,
                        });
                    }

                    // Append new events from the server
                    if (result.newEvents && result.newEvents.length > 0) {
                        this.events.push(...result.newEvents);
                        this.events.sort((a, b) => a.minute - b.minute);
                    }

                    // Reset lastRevealedIndex
                    this.lastRevealedIndex = -1;
                    for (let i = 0; i < this.events.length; i++) {
                        if (this.events[i].minute <= this.currentMinute) {
                            this.lastRevealedIndex = i;
                        } else {
                            break;
                        }
                    }

                    // Update the final score
                    this.finalHomeScore = result.newScore.home;
                    this.finalAwayScore = result.newScore.away;

                    // Recalculate current displayed score
                    this.recalculateScore();
                }

                // Close the panel and resume
                this.closeTacticalPanel();
            } catch (err) {
                console.error('Substitution request failed:', err);
            } finally {
                this.subProcessing = false;
            }
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

        getPositionBadgeColor(group) {
            const colors = {
                'Goalkeeper': 'bg-amber-500',
                'Defender': 'bg-blue-600',
                'Midfielder': 'bg-emerald-600',
                'Forward': 'bg-red-600',
            };
            return colors[group] || 'bg-emerald-600';
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

        destroy() {
            if (this._animFrame) {
                cancelAnimationFrame(this._animFrame);
            }
            clearTimeout(this.pauseTimer);
            clearTimeout(this._kickoffTimeout);
            clearTimeout(this._startETTimeout);
            clearTimeout(this._penaltyRevealTimer);
            document.body.classList.remove('overflow-y-hidden');
        },
    };
}
