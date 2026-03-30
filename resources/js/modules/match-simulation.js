/**
 * Match simulation module.
 *
 * Manages the match animation loop: clock progression, event reveal,
 * phase transitions (half-time, full-time, extra time), possession
 * fluctuation, score tracking, and other match tickers.
 *
 * This is a single cohesive system — clock, events, and ET are tightly
 * coupled through the animation frame and phase state machine.
 *
 * @param {Function} ctx - Returns the Alpine component instance
 */
export function createMatchSimulation(ctx) {
    let _lastTick = null;
    let _animFrame = null;

    /**
     * Track user-team substitution events as they are revealed during animation.
     * Populates substitutionsMade progressively so the lineup display stays in
     * sync with the animation clock instead of showing all subs from minute 0.
     */
    function trackSubstitutionIfNeeded(event) {
        const state = ctx();
        if (event.type === 'substitution' && event.teamId === state.userTeamId) {
            state.substitutionsMade.push({
                playerOutId: event.gamePlayerId,
                playerInId: event.metadata?.player_in_id ?? '',
                minute: event.minute,
                playerOutName: event.playerName ?? '',
                playerInName: event.playerInName ?? '',
            });
            const playerInId = event.metadata?.player_in_id;
            if (playerInId) {
                const benchPlayer = state.benchPlayers.find(p => p.id === playerInId);
                if (benchPlayer) {
                    benchPlayer.minuteEntered = event.minute;
                }
            }
        }
    }
    let _kickoffTimeout = null;
    let _startETTimeout = null;

    // =========================================================================
    // Animation loop
    // =========================================================================

    function tick(now) {
        const state = ctx();

        if (state.phase === 'full_time' || state.phase === 'pre_match'
            || state.phase === 'going_to_extra_time' || state.phase === 'penalties') {
            return;
        }

        if (state.isPaused || state.userPaused || state.tacticalPanelOpen || state.penaltyPickerOpen) {
            _lastTick = now;
            _animFrame = requestAnimationFrame(tick);
            return;
        }

        const deltaMs = now - _lastTick;
        _lastTick = now;

        const rate = state.speedRates[state.speed] || 1.5;
        const deltaMinutes = (deltaMs / 1000) * rate;

        const isExtraTime = state.phase === 'extra_time_first_half' || state.phase === 'extra_time_second_half';

        if (isExtraTime) {
            state.currentMinute = Math.min(state.currentMinute + deltaMinutes, 123);
        } else {
            state.currentMinute = Math.min(state.currentMinute + deltaMinutes, 93);
        }

        // Reveal events
        if (isExtraTime) {
            processETEvents();
        } else {
            processEvents();
        }

        // Update other match tickers
        updateOtherMatches();

        // Fluctuate possession display
        updatePossession();

        // Check for half-time
        if (state.phase === 'first_half' && state.currentMinute >= 45) {
            enterHalfTime();
            return;
        }

        // Check for end of regular time
        if (state.phase === 'second_half' && state.currentMinute >= 93) {
            enterRegularTimeEnd();
            return;
        }

        // Check for ET half-time
        if (state.phase === 'extra_time_first_half' && state.currentMinute >= 105) {
            enterETHalfTime();
            return;
        }

        // Check for end of extra time
        if (state.phase === 'extra_time_second_half' && state.currentMinute >= 123) {
            enterExtraTimeEnd();
            return;
        }

        _animFrame = requestAnimationFrame(tick);
    }

    // =========================================================================
    // Event processing
    // =========================================================================

    function synthesizeGoalsIfNeeded(events) {
        const state = ctx();
        // Count goals already present in events
        let existingHomeGoals = 0;
        let existingAwayGoals = 0;
        for (const e of events) {
            if (e.type === 'goal') {
                if (e.teamId === state.homeTeamId) existingHomeGoals++;
                else existingAwayGoals++;
            } else if (e.type === 'own_goal') {
                if (e.teamId === state.awayTeamId) existingHomeGoals++;
                else existingAwayGoals++;
            }
        }

        const missingHome = state.finalHomeScore - existingHomeGoals;
        const missingAway = state.finalAwayScore - existingAwayGoals;

        if (missingHome <= 0 && missingAway <= 0) {
            return events;
        }

        // Generate synthetic goals spread across the match
        const synthetic = [];
        const totalMissing = Math.max(0, missingHome) + Math.max(0, missingAway);
        const slotSize = 80 / (totalMissing + 1);

        let slot = 0;
        for (let i = 0; i < Math.max(0, missingHome); i++) {
            slot++;
            const minute = Math.round(8 + slotSize * slot + (Math.random() * slotSize * 0.4 - slotSize * 0.2));
            synthetic.push({
                minute: Math.max(1, Math.min(90, minute)),
                type: 'goal',
                playerName: state.homeTeamName,
                teamId: state.homeTeamId,
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
                playerName: state.awayTeamName,
                teamId: state.awayTeamId,
                gamePlayerId: null,
                metadata: {},
            });
        }

        return [...events, ...synthetic].sort((a, b) => a.minute - b.minute);
    }

    function processEvents() {
        const state = ctx();
        for (let i = state.lastRevealedIndex + 1; i < state.events.length; i++) {
            const event = state.events[i];
            if (event.minute <= state.currentMinute) {
                revealEvent(event, i);
            } else {
                break;
            }
        }
    }

    function processETEvents() {
        const state = ctx();
        for (let i = state.lastRevealedETIndex + 1; i < state.extraTimeEvents.length; i++) {
            const event = state.extraTimeEvents[i];
            if (event.minute <= state.currentMinute) {
                revealETEvent(event, i);
            } else {
                break;
            }
        }
    }

    function revealEvent(event, index) {
        const state = ctx();
        state.lastRevealedIndex = index;
        state.revealedEvents.unshift(event);
        state.latestEvent = event;
        trackSubstitutionIfNeeded(event);

        if (event.type === 'goal' || event.type === 'own_goal') {
            updateScore(event);
            triggerGoalFlash();
            pauseForDrama(1500);
        } else {
            pauseForDrama(1500);
        }

        // Auto-open tactical panel on substitutions tab when user's player gets injured
        if (event.type === 'injury' && event.teamId === state.userTeamId && state.canSubstitute && state.hasWindowsLeft) {
            state.injuryAlertPlayer = event.playerName;
            state.openTacticalPanel('substitutions', true);
            const injured = state.availableLineupForPicker.find(p => p.id === event.gamePlayerId);
            if (injured) {
                state.selectedPlayerOut = injured;
                state.livePitchSelectedOutId = injured.id;
            }
        }
    }

    function revealETEvent(event, index) {
        const state = ctx();
        state.lastRevealedETIndex = index;
        state.revealedEvents.unshift(event);
        state.latestEvent = event;
        trackSubstitutionIfNeeded(event);

        if (event.type === 'goal' || event.type === 'own_goal') {
            updateScore(event);
            triggerGoalFlash();
            pauseForDrama(1500);
        } else {
            pauseForDrama(1500);
        }
    }

    function updateScore(event) {
        const state = ctx();
        const isHomeGoal =
            (event.type === 'goal' && event.teamId === state.homeTeamId) ||
            (event.type === 'own_goal' && event.teamId === state.awayTeamId);

        if (isHomeGoal) {
            state.homeScore++;
        } else {
            state.awayScore++;
        }
    }

    function triggerGoalFlash() {
        const state = ctx();
        state.goalFlash = true;
        setTimeout(() => {
            state.goalFlash = false;
        }, 800);
    }

    function pauseForDrama(ms) {
        const state = ctx();
        state.isPaused = true;
        clearTimeout(state.pauseTimer);
        state.pauseTimer = setTimeout(() => {
            state.isPaused = false;
        }, ms);
    }

    function recalculateScore() {
        const state = ctx();
        let home = 0;
        let away = 0;
        for (const event of state.revealedEvents) {
            if (event.type === 'goal') {
                if (event.teamId === state.homeTeamId) home++;
                else away++;
            } else if (event.type === 'own_goal') {
                if (event.teamId === state.homeTeamId) away++;
                else home++;
            }
        }
        state.homeScore = home;
        state.awayScore = away;
    }

    // =========================================================================
    // Phase transitions
    // =========================================================================

    function enterHalfTime() {
        const state = ctx();
        state.currentMinute = 45;
        state.phase = 'half_time';

        setTimeout(() => {
            state.phase = 'second_half';
            _lastTick = performance.now();
            _animFrame = requestAnimationFrame(tick);
        }, 1500);
    }

    function enterRegularTimeEnd() {
        const state = ctx();
        state.currentMinute = 90;

        // Reveal any remaining regular time events
        for (let i = state.lastRevealedIndex + 1; i < state.events.length; i++) {
            const event = state.events[i];
            state.lastRevealedIndex = i;
            state.revealedEvents.unshift(event);
            trackSubstitutionIfNeeded(event);
            if (event.type === 'goal' || event.type === 'own_goal') {
                updateScore(event);
            }
        }

        // Ensure regular time scores match
        state.homeScore = state.finalHomeScore;
        state.awayScore = state.finalAwayScore;

        // Check if this is a knockout match and we need extra time
        if (state.isKnockout && needsExtraTime()) {
            if (state.preloadedExtraTimeData) {
                state.phase = 'going_to_extra_time';
                _startETTimeout = setTimeout(() => startExtraTime(), 2000);
            } else {
                state.phase = 'going_to_extra_time';
                fetchExtraTime();
            }
        } else {
            enterFullTime();
        }
    }

    function enterETHalfTime() {
        const state = ctx();
        state.currentMinute = 105;
        state.phase = 'extra_time_half_time';

        setTimeout(() => {
            state.phase = 'extra_time_second_half';
            _lastTick = performance.now();
            _animFrame = requestAnimationFrame(tick);
        }, 1500);
    }

    function enterExtraTimeEnd() {
        const state = ctx();
        clearTimeout(_startETTimeout);
        state.currentMinute = 120;

        // Reveal any remaining ET events
        for (let i = state.lastRevealedETIndex + 1; i < state.extraTimeEvents.length; i++) {
            const event = state.extraTimeEvents[i];
            state.lastRevealedETIndex = i;
            state.revealedEvents.unshift(event);
            trackSubstitutionIfNeeded(event);
            if (event.type === 'goal' || event.type === 'own_goal') {
                updateScore(event);
            }
        }

        // Ensure ET scores match
        state.homeScore = state.finalHomeScore + state.etHomeScore;
        state.awayScore = state.finalAwayScore + state.etAwayScore;

        if (state._needsPenalties) {
            state.phase = 'penalties';
            state.openPenaltyPicker();
        } else if (state.penaltyResult) {
            state.phase = 'penalties';
            setTimeout(() => enterFullTime(), 3000);
        } else {
            enterFullTime();
        }
    }

    function enterFullTime() {
        const state = ctx();
        state.phase = 'full_time';

        if (!state.hasExtraTime) {
            state.currentMinute = 90;
            state.homeScore = state.finalHomeScore;
            state.awayScore = state.finalAwayScore;
            for (let i = state.lastRevealedIndex + 1; i < state.events.length; i++) {
                const event = state.events[i];
                state.revealedEvents.unshift(event);
                trackSubstitutionIfNeeded(event);
            }
            state.lastRevealedIndex = state.events.length - 1;
        } else {
            state.currentMinute = 120;
        }

        if (_animFrame) {
            cancelAnimationFrame(_animFrame);
        }
    }

    // =========================================================================
    // Extra time
    // =========================================================================

    function needsExtraTime() {
        const state = ctx();
        if (state.twoLeggedInfo) {
            const firstLegHome = state.twoLeggedInfo.firstLegHomeScore;
            const firstLegAway = state.twoLeggedInfo.firstLegAwayScore;
            const tieHomeTotal = firstLegHome + state.finalAwayScore;
            const tieAwayTotal = firstLegAway + state.finalHomeScore;
            return tieHomeTotal === tieAwayTotal;
        }
        return state.finalHomeScore === state.finalAwayScore;
    }

    async function fetchExtraTime() {
        const state = ctx();
        state.extraTimeLoading = true;

        try {
            const response = await fetch(state.extraTimeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': state.csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({}),
            });

            if (!response.ok) {
                console.error('Extra time request failed');
                enterFullTime();
                return;
            }

            const result = await response.json();

            if (!result.needed) {
                enterFullTime();
                return;
            }

            state.extraTimeEvents = result.extraTimeEvents || [];
            state.etHomeScore = result.homeScoreET || 0;
            state.etAwayScore = result.awayScoreET || 0;
            state._needsPenalties = result.needsPenalties || false;

            if (result.homePossession !== undefined) {
                state._basePossession = result.homePossession;
                state._possessionDisplay = result.homePossession;
                state.homePossession = result.homePossession;
                state.awayPossession = result.awayPossession;
                resetPossessionTarget();
            }

            _startETTimeout = setTimeout(() => startExtraTime(), 2000);
        } catch (err) {
            console.error('Extra time request failed:', err);
            enterFullTime();
        } finally {
            state.extraTimeLoading = false;
        }
    }

    function startExtraTime() {
        const state = ctx();
        state.currentMinute = 91;
        state.phase = 'extra_time_first_half';
        state.lastRevealedETIndex = -1;
        _lastTick = performance.now();
        _animFrame = requestAnimationFrame(tick);
    }

    function skipExtraTime() {
        const state = ctx();
        clearTimeout(_startETTimeout);
        state._skippingToEnd = false;
        state.currentMinute = 123;

        // Reveal all ET events
        for (let i = state.lastRevealedETIndex + 1; i < state.extraTimeEvents.length; i++) {
            const event = state.extraTimeEvents[i];
            state.lastRevealedETIndex = i;
            state.revealedEvents.unshift(event);
            trackSubstitutionIfNeeded(event);
            if (event.type === 'goal' || event.type === 'own_goal') {
                updateScore(event);
            }
        }

        state.homeScore = state.finalHomeScore + state.etHomeScore;
        state.awayScore = state.finalAwayScore + state.etAwayScore;

        if (state._needsPenalties) {
            state.phase = 'penalties';
            state.openPenaltyPicker();
        } else if (state.penaltyResult) {
            state.phase = 'penalties';
            setTimeout(() => enterFullTime(), 2000);
        } else {
            enterFullTime();
        }
    }

    // =========================================================================
    // Possession
    // =========================================================================

    function updatePossession() {
        const state = ctx();
        if (state.currentMinute >= state._possessionNextShift) {
            const swing = (Math.random() - 0.5) * 16;
            state._possessionTarget = Math.max(25, Math.min(75, state._basePossession + swing));
            state._possessionNextShift = state.currentMinute + 2 + Math.random() * 2;
        }
        state._possessionDisplay += (state._possessionTarget - state._possessionDisplay) * 0.03;
        const rounded = Math.round(state._possessionDisplay);
        if (rounded !== state.homePossession) {
            state.homePossession = rounded;
            state.awayPossession = 100 - rounded;
        }
    }

    function resetPossessionTarget() {
        const state = ctx();
        state._possessionTarget = state._basePossession;
        state._possessionNextShift = state.currentMinute + 1 + Math.random() * 2;
    }

    // =========================================================================
    // Other matches
    // =========================================================================

    function updateOtherMatches() {
        const state = ctx();
        for (let i = 0; i < state.otherMatches.length; i++) {
            const match = state.otherMatches[i];
            let home = 0;
            let away = 0;
            for (const goal of match.goalMinutes) {
                if (goal.minute <= state.currentMinute) {
                    if (goal.side === 'home') home++;
                    else away++;
                }
            }
            state.otherMatchScores[i] = { homeScore: home, awayScore: away };
        }
    }

    // =========================================================================
    // Speed controls
    // =========================================================================

    function togglePause() {
        ctx().userPaused = !ctx().userPaused;
    }

    function setSpeed(s) {
        const state = ctx();
        state.speed = s;
        localStorage.setItem('liveMatchSpeed', s);
    }

    function skipToEnd() {
        const state = ctx();
        state.userPaused = false;

        // Cancel the kickoff timeout if skip is pressed during pre_match
        if (_kickoffTimeout) {
            clearTimeout(_kickoffTimeout);
            _kickoffTimeout = null;
        }

        // If penalties are being animated, delegate to penalty module
        if (state.skipPenaltyReveal()) return;

        if (state.isKnockout && !state.hasExtraTime && !state._skippingToEnd) {
            state._skippingToEnd = true;
            state.currentMinute = 93;
            updateOtherMatches();
            enterRegularTimeEnd();

            if (state.phase === 'going_to_extra_time') {
                const waitForET = () => {
                    if (state.extraTimeEvents.length > 0 || state._needsPenalties || state.etHomeScore > 0 || state.etAwayScore > 0) {
                        skipExtraTime();
                    } else if (state.phase === 'going_to_extra_time') {
                        setTimeout(waitForET, 100);
                    }
                };
                waitForET();
            }
            return;
        }

        if (state.hasExtraTime && state.phase === 'going_to_extra_time') {
            clearTimeout(_startETTimeout);
            skipExtraTime();
            return;
        }

        if (state.hasExtraTime && (state.phase === 'extra_time_first_half'
            || state.phase === 'extra_time_second_half' || state.phase === 'extra_time_half_time')) {
            skipExtraTime();
            return;
        }

        state.currentMinute = 93;
        updateOtherMatches();
        enterFullTime();
    }

    // =========================================================================
    // Initialization (called from init)
    // =========================================================================

    function start() {
        const state = ctx();

        // Synthesize goals if events are empty but there's a final score
        state.events = synthesizeGoalsIfNeeded(state.events);

        // Brief delay before kickoff
        _kickoffTimeout = setTimeout(() => {
            _kickoffTimeout = null;
            state.phase = 'first_half';
            _lastTick = performance.now();
            _animFrame = requestAnimationFrame(tick);
        }, 1000);
    }

    function destroyTimers() {
        if (_animFrame) cancelAnimationFrame(_animFrame);
        clearTimeout(_kickoffTimeout);
        clearTimeout(_startETTimeout);
    }

    // =========================================================================
    // Public API
    // =========================================================================

    return {
        // Lifecycle
        startSimulation: start,
        _destroySimulationTimers: destroyTimers,

        // Speed controls
        togglePause,
        setSpeed,
        skipToEnd,

        // Phase transitions (some called externally by confirmAllChanges / penalty module)
        enterFullTime,

        // Event/score methods (called by confirmAllChanges)
        synthesizeGoalsIfNeeded,
        recalculateScore,
        resetPossessionTarget,
    };
}
