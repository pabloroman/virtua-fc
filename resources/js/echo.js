/**
 * Build an Echo instance configured for Laravel Reverb.
 *
 * This module never imports `laravel-echo` or `pusher-js` directly — that
 * confused Vite's dep-optimizer when the packages weren't yet in
 * node_modules and the resulting module-load failure cascaded into the
 * whole live-duel chain (Alpine never saw the liveDuel factory).
 *
 * Instead the duel views drop CDN script tags that publish `window.Echo`
 * and `window.Pusher` as globals. We consume them here. Returns null when
 * either the Reverb key isn't configured or the globals haven't loaded
 * yet — the page still renders, only real-time push is disabled.
 */
export function createEcho({ key, host, port, scheme }) {
    if (!key) {
        console.warn('[live-duel] Reverb key missing; skipping Echo wiring.');
        return null;
    }
    if (typeof window.Echo === 'undefined' || typeof window.Pusher === 'undefined') {
        console.warn('[live-duel] Echo / Pusher globals not loaded; real-time updates disabled.');
        return null;
    }

    return new window.Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host ?? window.location.hostname,
        wsPort: port ?? 80,
        wssPort: port ?? 443,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
