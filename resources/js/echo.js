import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * Build an Echo instance configured for Laravel Reverb. Returns null if the
 * required env keys aren't set so consumers can degrade gracefully (the
 * live duel renders state from the server and just won't receive push
 * updates).
 */
export function createEcho({ key, host, port, scheme }) {
    if (!key) {
        console.warn('[live-duel] Reverb key missing; skipping Echo wiring.');
        return null;
    }

    return new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host ?? window.location.hostname,
        wsPort: port ?? 80,
        wssPort: port ?? 443,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
