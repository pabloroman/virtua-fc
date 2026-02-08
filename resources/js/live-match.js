export default function liveMatch(config) {
    return {
        // Config (from server)
        events: config.events || [],
        homeTeamId: config.homeTeamId,
        awayTeamId: config.awayTeamId,
        finalHomeScore: config.finalHomeScore,
        finalAwayScore: config.finalAwayScore,
        otherMatches: config.otherMatches || [],

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

        // Ticker state for other matches
        otherMatchScores: [],

        // Animation loop
        _lastTick: null,
        _animFrame: null,

        // Speed presets: match minutes per real second
        speedRates: {
            1: 1.5,   // 60s for full match
            2: 3.0,   // 30s
            4: 6.0,   // 15s
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

            if (this.isPaused) {
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
                this.pauseForDrama(2000);
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
            }, 3000);
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
            return this.phase === 'first_half' || this.phase === 'second_half';
        },

        getEventIcon(type) {
            switch (type) {
                case 'goal': return '⚽';
                case 'own_goal': return '⚽';
                case 'yellow_card': return '🟨';
                case 'red_card': return '🟥';
                case 'injury': return '🏥';
                default: return '•';
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
