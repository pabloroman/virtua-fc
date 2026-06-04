/**
 * Build an Echo instance configured for Laravel Reverb.
 *
 * `laravel-echo` and `pusher-js` are bundled through Vite (installed as npm
 * deps by `php artisan install:broadcasting`). Pusher is exposed on `window`
 * because Echo's reverb broadcaster resolves its client from `window.Pusher`.
 *
 * Each live-duel view builds its own Echo from server-injected Reverb config
 * (key/host/port/scheme), so this is a factory rather than a shared
 * singleton. Returns null when the Reverb key isn't configured — the page
 * still renders, only real-time push is disabled.
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

export function createEcho({ key, host, port, scheme }) {
    if (!key) {
        console.warn('[live-duel] Reverb key missing; skipping Echo wiring.');
        return null;
    }

    // Pick the right default per scheme so a single REVERB_PORT (e.g. 8080)
    // doesn't get used for both wss and ws ports unrelated to the configured
    // scheme.
    const isHttps = scheme === 'https';
    const defaultPort = isHttps ? 443 : 80;
    const effectivePort = port ?? defaultPort;

    return new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host ?? window.location.hostname,
        wsPort: isHttps ? 80 : effectivePort,
        wssPort: isHttps ? effectivePort : 443,
        forceTLS: isHttps,
        enabledTransports: ['ws', 'wss'],
    });
}
