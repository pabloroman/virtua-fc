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
        maxWindows: config.maxWindows || 3,

        // Tactical config
        activeFormation: config.activeFormation || '4-4-2',
        activeMentality: config.activeMentality || 'balanced',
        availableFormations: config.availableFormations || [],
        availableMentalities: config.availableMentalities || [],
        tacticsUrl: config.tacticsUrl || '',

        // Tactical change state
        pendingFormation: null,
        pendingMentality: null,
        tacticsProcessing: false,

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

        // Tactical panel state
        tacticalPanelOpen: false,
        tacticalTab: 'substitutions',

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

            if (this.isPaused || this.tacticalPanelOpen) {
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

            // Auto-open tactical panel on substitutions tab when user's player gets injured
            if (event.type === 'injury' && event.teamId === this.userTeamId && this.canSubstitute && this.hasWindowsLeft) {
                this.openTacticalPanel('substitutions');
                // Pre-select the injured player as "player out"
                const injured = this.availableLineupForPicker.find(p => p.id === event.gamePlayerId);
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

        // =============================
        // Tactical panel methods
        // =============================

        openTacticalPanel(tab = 'substitutions') {
            this.tacticalTab = tab;
            this.tacticalPanelOpen = true;
            this.selectedPlayerOut = null;
            this.selectedPlayerIn = null;
            this.pendingSubs = [];
            this.pendingFormation = null;
            this.pendingMentality = null;
            document.body.classList.add('overflow-y-hidden');
        },

        closeTacticalPanel() {
            this.tacticalPanelOpen = false;
            this.selectedPlayerOut = null;
            this.selectedPlayerIn = null;
            this.pendingSubs = [];
            this.pendingFormation = null;
            this.pendingMentality = null;
            document.body.classList.remove('overflow-y-hidden');
        },

        get mentalityLabel() {
            const m = this.availableMentalities.find(m => m.value === this.activeMentality);
            return m ? m.label : this.activeMentality;
        },

        get hasTacticalChanges() {
            return (this.pendingFormation !== null && this.pendingFormation !== this.activeFormation)
                || (this.pendingMentality !== null && this.pendingMentality !== this.activeMentality);
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

                // Update active tactics
                if (result.formation) {
                    this.activeFormation = result.formation;
                }
                if (result.mentality) {
                    this.activeMentality = result.mentality;
                }

                // Remove future unrevealed events from the array
                this.events = this.events.filter(e => e.minute <= minute);

                // Remove future events from revealedEvents too
                this.revealedEvents = this.revealedEvents.filter(e => e.minute <= minute);

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

        get windowsUsed() {
            // Count unique minutes in substitutionsMade — each unique minute = one window
            const minutes = new Set(this.substitutionsMade.map(s => s.minute));
            return minutes.size;
        },

        get hasWindowsLeft() {
            return this.windowsUsed < this.maxWindows;
        },

        get subsRemaining() {
            return this.maxSubstitutions - this.substitutionsMade.length - this.pendingSubs.length;
        },

        get canSubstitute() {
            return this.substitutionsMade.length + this.pendingSubs.length < this.maxSubstitutions;
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

                // Record all substitutions in the batch
                for (const sub of result.substitutions) {
                    this.substitutionsMade.push({
                        playerOutId: sub.playerOutId,
                        playerInId: sub.playerInId,
                        playerOutName: sub.playerOutName,
                        playerInName: sub.playerInName,
                        minute: subMinute,
                    });
                }

                // Remove future unrevealed events from the array
                this.events = this.events.filter(e => e.minute <= subMinute);

                // Remove future events from revealedEvents too
                this.revealedEvents = this.revealedEvents.filter(e => e.minute <= subMinute);

                // Add substitution events to the feed (one per sub in the batch)
                for (const sub of result.substitutions) {
                    this.revealedEvents.unshift({
                        minute: subMinute,
                        type: 'substitution',
                        playerName: sub.playerOutName,
                        playerInName: sub.playerInName,
                        teamId: sub.teamId,
                    });
                }

                // Append new events from the server (they'll be revealed as the clock advances)
                if (result.newEvents && result.newEvents.length > 0) {
                    this.events.push(...result.newEvents);
                    this.events.sort((a, b) => a.minute - b.minute);
                }

                // Reset lastRevealedIndex to account for the modified events array
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
            if (this.phase === 'full_time') return '90';
            return String(Math.min(m, 90));
        },

        get timelineProgress() {
            return Math.min((this.currentMinute / 90) * 100, 100);
        },

        get isRunning() {
            return (this.phase === 'first_half' || this.phase === 'second_half') && !this.tacticalPanelOpen;
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
            document.body.classList.remove('overflow-y-hidden');
        },
    };
}
