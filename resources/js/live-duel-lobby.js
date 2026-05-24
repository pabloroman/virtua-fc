import { createEcho } from './echo';

/**
 * Lobby Alpine component for the live duel.
 *
 * Subscribes to the session's presence channel and listens for two events:
 *  - match.guest_joined  → reload (guest needs to be redirected to picker)
 *  - match.team_picked   → reload when both teams are now picked (kickoff)
 *  - match.started       → reload to render the live match view
 *
 * Keep this dead simple — the lobby has very few states and full-page
 * reloads on transitions avoid SPA-style state-management churn.
 */
export default function liveDuelLobby(config) {
    return {
        ...config,
        copied: false,
        echo: null,
        channel: null,

        async init() {
            this.echo = await createEcho({
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
                .listen('.match.guest_joined', () => {
                    window.location.reload();
                })
                .listen('.match.team_picked', (payload) => {
                    if (payload.both_picked) {
                        // Kickoff is imminent; the next match.started broadcast
                        // will land in a moment and we'll reload from that.
                    } else {
                        window.location.reload();
                    }
                })
                .listen('.match.started', () => {
                    window.location.reload();
                });
        },

        async copyShareUrl() {
            try {
                await navigator.clipboard.writeText(this.shareUrl);
            } catch (e) {
                // Fallback for older browsers
                const tmp = document.createElement('input');
                tmp.value = this.shareUrl;
                document.body.appendChild(tmp);
                tmp.select();
                document.execCommand('copy');
                document.body.removeChild(tmp);
            }
            this.copied = true;
            setTimeout(() => { this.copied = false; }, 2000);
        },
    };
}
