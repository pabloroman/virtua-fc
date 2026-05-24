import { createEcho } from './echo';

/**
 * Live duel Alpine component.
 *
 * Subscribes to the session's presence channel and renders match state from
 * server-pushed events. Local clock interpolation between server ticks keeps
 * the minute counter feeling continuous; goals/cards/halftime arrive as
 * pause broadcasts.
 */
export default function liveDuel(config) {
    return {
        // Static config
        sessionId: config.sessionId,
        viewerRole: config.viewerRole, // 'host' | 'guest'
        viewerSide: config.viewerSide, // 'home' | 'away'
        csrfToken: config.csrfToken,
        reverbKey: config.reverbKey,
        reverbHost: config.reverbHost,
        reverbPort: config.reverbPort,
        reverbScheme: config.reverbScheme,
        labels: config.labels,

        // Reactive state
        phase: config.initial.phase,
        homeScore: config.initial.homeScore,
        awayScore: config.initial.awayScore,
        currentMinute: config.initial.currentMinute,
        displayMinute: config.initial.currentMinute,
        pauseReason: config.initial.pauseReason,
        hostBot: config.initial.hostBot,
        guestBot: config.initial.guestBot,
        events: [],
        myAcked: false,
        opponentPreparingSub: false,
        connected: false,

        // Squads
        hostSquad: config.initial.hostSquad || {},
        guestSquad: config.initial.guestSquad || {},
        contextOnPitch: { home: [], away: [], homeBench: [], awayBench: [] },

        // Sub modal
        subModalOpen: false,
        subPlayerOut: '',
        subPlayerIn: '',
        subError: '',
        subsUsed: 0,

        echo: null,
        channel: null,
        clockTimer: null,

        init() {
            this.seedFromEventLog(config.initial.eventLog || []);
            this.computeOnPitchFromSquads();
            this.startClockInterpolation();

            this.echo = createEcho({
                key: this.reverbKey,
                host: this.reverbHost,
                port: this.reverbPort ? Number(this.reverbPort) : undefined,
                scheme: this.reverbScheme,
            });

            if (!this.echo) {
                return;
            }

            this.channel = this.echo.join(`live-match.${this.sessionId}`);

            this.channel
                .here(() => { this.connected = true; })
                .joining(() => { this.connected = true; })
                .leaving(() => {})
                .listen('.match.started', (e) => {
                    this.phase = 'live';
                    this.currentMinute = e.current_minute ?? 0;
                    this.displayMinute = this.currentMinute;
                })
                .listen('.match.event', (e) => {
                    this.homeScore = e.home_score;
                    this.awayScore = e.away_score;
                    this.currentMinute = e.current_minute ?? this.currentMinute;
                    this.appendEvent(e.event);
                })
                .listen('.match.paused', (e) => {
                    this.phase = 'paused';
                    this.pauseReason = e.pause_reason;
                    this.myAcked = false;
                })
                .listen('.match.resumed', (e) => {
                    this.phase = 'live';
                    this.pauseReason = null;
                    this.myAcked = false;
                    this.currentMinute = e.current_minute ?? this.currentMinute;
                })
                .listen('.match.ended', (e) => {
                    this.phase = 'finished';
                    this.homeScore = e.home_score;
                    this.awayScore = e.away_score;
                })
                .listen('.match.bot_takeover', (e) => {
                    if (e.side === 'home') this.hostBot = true;
                    else this.guestBot = true;
                })
                .listen('.match.action_queued', (e) => {
                    if (e.side !== this.viewerSide) {
                        this.opponentPreparingSub = true;
                        setTimeout(() => { this.opponentPreparingSub = false; }, 4000);
                    }
                });
        },

        seedFromEventLog(log) {
            this.events = log.map((entry, idx) => ({
                id: `${entry.minute}-${idx}-${entry.type}`,
                minute: entry.minute,
                type: entry.type,
                side: entry.side,
                team_id: entry.team_id,
                game_player_id: entry.game_player_id,
                metadata: entry.metadata,
            }));
            // Count subs used on viewer's side.
            this.subsUsed = log.filter((e) => e.type === 'substitution' && e.side === this.viewerSide).length;
        },

        appendEvent(event) {
            this.events.push({
                id: `${event.minute}-${this.events.length}-${event.type}`,
                ...event,
            });
            if (event.type === 'substitution' && event.side === this.viewerSide) {
                this.subsUsed += 1;
            }
            this.recomputeOnPitchFromEvents();
        },

        computeOnPitchFromSquads() {
            this.contextOnPitch = {
                home: this.hostSquad.starting_xi || [],
                away: this.guestSquad.starting_xi || [],
                homeBench: this.hostSquad.bench || [],
                awayBench: this.guestSquad.bench || [],
            };
            this.applySubsFromEvents();
        },

        recomputeOnPitchFromEvents() {
            this.computeOnPitchFromSquads();
        },

        applySubsFromEvents() {
            for (const event of this.events) {
                if (event.type !== 'substitution') continue;
                const out = event.game_player_id;
                const inId = event.metadata?.player_in_id;
                if (!out || !inId) continue;
                const sideKey = event.side === 'home' ? 'home' : 'away';
                const benchKey = sideKey === 'home' ? 'homeBench' : 'awayBench';
                const onPitch = this.contextOnPitch[sideKey];
                const bench = this.contextOnPitch[benchKey];
                const subIn = bench.find((p) => p.id === inId);
                if (!subIn) continue;
                const idx = onPitch.findIndex((p) => p.id === out);
                if (idx === -1) continue;
                onPitch[idx] = subIn;
                this.contextOnPitch[benchKey] = bench.filter((p) => p.id !== inId);
            }
        },

        get myOnPitch() {
            return this.viewerSide === 'home' ? this.contextOnPitch.home : this.contextOnPitch.away;
        },

        get myBench() {
            return this.viewerSide === 'home' ? this.contextOnPitch.homeBench : this.contextOnPitch.awayBench;
        },

        eventIcon(type) {
            return {
                goal: '⚽',
                own_goal: '⚽',
                yellow_card: '🟨',
                red_card: '🟥',
                injury: '🚑',
                substitution: '🔄',
                penalty_missed: '❌',
                assist: '🅰️',
            }[type] || '·';
        },

        eventLabel(event) {
            const label = {
                goal: 'Goal',
                own_goal: 'Own goal',
                yellow_card: 'Yellow card',
                red_card: 'Red card',
                injury: 'Injury',
                substitution: 'Substitution',
                penalty_missed: 'Penalty missed',
                assist: 'Assist',
            }[event.type] || event.type;
            const side = event.side === 'home' ? '🟦' : '🟥';
            return `${side} ${label}`;
        },

        pauseLabel() {
            return {
                goal: this.labels.pauseReasonGoal,
                red_card: this.labels.pauseReasonRedCard,
                injury: this.labels.pauseReasonInjury,
                halftime: this.labels.pauseReasonHalftime,
            }[this.pauseReason] || '';
        },

        pauseIcon() {
            return {
                goal: '⚽',
                red_card: '🟥',
                injury: '🚑',
                halftime: '⏸️',
            }[this.pauseReason] || '⏸️';
        },

        startClockInterpolation() {
            // Approximate sim-minute progression between server pushes.
            // 1 real second ≈ 1.5 sim-minutes (matches WINDOW_DELAY_SECONDS).
            this.clockTimer = setInterval(() => {
                if (this.phase !== 'live') return;
                if (this.displayMinute < 93) {
                    this.displayMinute = Math.min(this.displayMinute + 0.25, 93);
                }
            }, 250);
        },

        openSubModal() {
            this.subPlayerOut = '';
            this.subPlayerIn = '';
            this.subError = '';
            this.subModalOpen = true;
        },

        async confirmSub() {
            this.subError = '';
            try {
                const res = await fetch(`/live/duel/${this.sessionId}/actions`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        type: 'sub',
                        payload: { player_out_id: this.subPlayerOut, player_in_id: this.subPlayerIn },
                    }),
                });
                if (!res.ok) {
                    const body = await res.json().catch(() => ({}));
                    this.subError = body.error || `HTTP ${res.status}`;
                    return;
                }
                this.subModalOpen = false;
            } catch (e) {
                this.subError = e.message || 'Request failed';
            }
        },

        async ackPause() {
            if (this.myAcked) return;
            this.myAcked = true;
            try {
                await fetch(`/live/duel/${this.sessionId}/ack`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
            } catch (e) {
                this.myAcked = false;
            }
        },
    };
}
