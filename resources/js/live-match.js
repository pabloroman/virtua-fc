export default function liveMatch(config) {
    return {
        // Config (from server)
        events: config.events || [],
        homeTeamId: config.homeTeamId,
        awayTeamId: config.awayTeamId,
        finalHomeScore: config.finalHomeScore,
        finalAwayScore: config.finalAwayScore,
        otherMatches: config.otherMatches || [],
        homeTeamImage: config.homeTeamImage,
        awayTeamImage: config.awayTeamImage,
        userTeamId: config.userTeamId,

        // Substitution config
        lineupPlayers: config.lineupPlayers || [],
        benchPlayers: config.benchPlayers || [],
        substituteUrl: config.substituteUrl || '',
        csrfToken: config.csrfToken || '',
        maxSubstitutions: config.maxSubstitutions || 5,

        // Clock state
        currentMinute: 0,
        speed: 1,
        phase: 'pre_match', // pre_match, first_half, half_time, second_half, full_time
        isPaused: false,
        pauseTimer: null,

        // Derived state
        revealedEvents: [],
        homeScore: 0,
        awayScore: 0,
        lastRevealedIndex: -1,
        goalFlash: false,
        latestEvent: null,

        // Substitution state
        subPanelOpen: false,
        selectedPlayerOut: null,
        selectedPlayerIn: null,
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

            // Brief delay before kickoff
            setTimeout(() => {
                this.phase = 'first_half';
                this._lastTick = performance.now();
                this._animFrame = requestAnimationFrame(this.tick.bind(this));
            }, 1000);
        },

        tick(now) {
            if (this.phase === 'full_time' || this.phase === 'pre_match') {
                return;
            }

            if (this.isPaused || this.subPanelOpen) {
                this._lastTick = now;
                this._animFrame = requestAnimationFrame(this.tick.bind(this));
                return;
            }

            const deltaMs = now - this._lastTick;
            this._lastTick = now;

            const rate = this.speedRates[this.speed] || 1.5;
            const deltaMinutes = (deltaMs / 1000) * rate;

            this.currentMinute = Math.min(this.currentMinute + deltaMinutes, 93);

            // Reveal events
            this.processEvents();

            // Update other match tickers
            this.updateOtherMatches();

            // Check for half-time
            if (this.phase === 'first_half' && this.currentMinute >= 45) {
                this.enterHalfTime();
                return;
            }

            // Check for full-time
            if (this.phase === 'second_half' && this.currentMinute >= 93) {
                this.enterFullTime();
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

        revealEvent(event, index) {
            this.lastRevealedIndex = index;
            this.revealedEvents.unshift(event); // newest first
            this.latestEvent = event;

            if (event.type === 'goal' || event.type === 'own_goal') {
                this.updateScore(event);
                this.triggerGoalFlash();
                this.pauseForDrama(1500);
            }

            // Auto-open substitution panel when user's player gets injured
            if (event.type === 'injury' && event.teamId === this.userTeamId && this.substitutionsMade.length < this.maxSubstitutions) {
                this.openSubPanel();
                // Pre-select the injured player as "player out"
                const injured = this.availableLineupPlayers.find(p => p.id === event.gamePlayerId);
                if (injured) {
                    this.selectedPlayerOut = injured;
                }
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

        enterFullTime() {
            this.currentMinute = 90;
            this.phase = 'full_time';
            // Ensure final scores match
            this.homeScore = this.finalHomeScore;
            this.awayScore = this.finalAwayScore;
            // Reveal any remaining events
            for (let i = this.lastRevealedIndex + 1; i < this.events.length; i++) {
                this.revealedEvents.unshift(this.events[i]);
            }
            this.lastRevealedIndex = this.events.length - 1;

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
            this.currentMinute = 93;
            this.processEvents();
            this.updateOtherMatches();
            this.enterFullTime();
        },

        // =====================
        // Substitution methods
        // =====================

        openSubPanel() {
            if (this.substitutionsMade.length >= this.maxSubstitutions) return;
            this.subPanelOpen = true;
            this.selectedPlayerOut = null;
            this.selectedPlayerIn = null;
            // Pause the match clock while the sub panel is open
        },

        closeSubPanel() {
            this.subPanelOpen = false;
            this.selectedPlayerOut = null;
            this.selectedPlayerIn = null;
        },

        get redCardedPlayerIds() {
            return this.revealedEvents
                .filter(e => e.type === 'red_card' && e.teamId === this.userTeamId)
                .map(e => e.gamePlayerId);
        },

        get availableLineupPlayers() {
            // Start with original lineup, apply substitutions
            const subbedOutIds = this.substitutionsMade.map(s => s.playerOutId);
            const subbedInIds = this.substitutionsMade.map(s => s.playerInId);
            const redCarded = this.redCardedPlayerIds;

            // Original lineup players still on pitch (not subbed out, not red-carded)
            const onPitch = this.lineupPlayers.filter(p => !subbedOutIds.includes(p.id) && !redCarded.includes(p.id));

            // Players who came on as subs and are still on pitch (not red-carded)
            const subsOnPitch = this.benchPlayers.filter(p => subbedInIds.includes(p.id) && !subbedOutIds.includes(p.id) && !redCarded.includes(p.id));

            return [...onPitch, ...subsOnPitch].sort((a, b) => a.positionSort - b.positionSort);
        },

        get availableBenchPlayers() {
            // Bench players minus those already subbed in
            const subbedInIds = this.substitutionsMade.map(s => s.playerInId);
            return this.benchPlayers.filter(p => !subbedInIds.includes(p.id)).sort((a, b) => a.positionSort - b.positionSort);
        },

        async confirmSubstitution() {
            if (!this.selectedPlayerOut || !this.selectedPlayerIn || this.subProcessing) return;

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
                        playerOutId: this.selectedPlayerOut.id,
                        playerInId: this.selectedPlayerIn.id,
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

                // Record the substitution
                this.substitutionsMade.push({
                    playerOutId: this.selectedPlayerOut.id,
                    playerInId: this.selectedPlayerIn.id,
                    playerOutName: this.selectedPlayerOut.name,
                    playerInName: this.selectedPlayerIn.name,
                    minute: subMinute,
                });

                // Remove future unrevealed events from the array
                this.events = this.events.filter(e => e.minute <= subMinute);

                // Remove future events from revealedEvents too
                this.revealedEvents = this.revealedEvents.filter(e => e.minute <= subMinute);

                // Add the substitution event to the revealed events feed
                const subEvent = {
                    minute: subMinute,
                    type: 'substitution',
                    playerName: this.selectedPlayerOut.name,
                    playerInName: this.selectedPlayerIn.name,
                    teamId: result.substitution.teamId,
                };
                this.revealedEvents.unshift(subEvent);

                // Append new events from the server (they'll be revealed as the clock advances)
                if (result.newEvents && result.newEvents.length > 0) {
                    this.events.push(...result.newEvents);
                    // Re-sort all events by minute
                    this.events.sort((a, b) => a.minute - b.minute);
                }

                // Reset lastRevealedIndex to account for the modified events array
                // Find the last event that's <= current minute
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

                // Recalculate current displayed score from revealed goal events
                this.recalculateScore();

                // Close the panel and resume
                this.closeSubPanel();
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

        // Display helpers
        get displayMinute() {
            const m = Math.floor(this.currentMinute);
            if (this.phase === 'pre_match') return '0';
            if (this.phase === 'half_time') return '45';
            if (this.phase === 'full_time') return '90';
            return String(Math.min(m, 90));
        },

        get timelineProgress() {
            return Math.min((this.currentMinute / 90) * 100, 100);
        },

        get isRunning() {
            return (this.phase === 'first_half' || this.phase === 'second_half') && !this.subPanelOpen;
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
                // Own goal: the team recorded is the team of the scorer, but it benefits the other side
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

        get secondHalfEvents() {
            return this.revealedEvents.filter(e => e.minute > 45);
        },

        get firstHalfEvents() {
            return this.revealedEvents.filter(e => e.minute <= 45);
        },

        get showHalfTimeSeparator() {
            return this.phase === 'half_time' || this.phase === 'second_half' || this.phase === 'full_time';
        },

        getTimelineMarkers() {
            return this.revealedEvents
                .filter(e => e.type !== 'assist')
                .map(e => ({
                    position: (e.minute / 90) * 100,
                    type: e.type,
                    minute: e.minute,
                }));
        },

        destroy() {
            if (this._animFrame) {
                cancelAnimationFrame(this._animFrame);
            }
            clearTimeout(this.pauseTimer);
        },
    };
}
