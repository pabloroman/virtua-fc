/**
 * Build an Echo instance configured for Laravel Reverb.
 *
 * Imports of laravel-echo and pusher-js are deliberately dynamic so the
 * rest of the live-duel module loads even if those packages aren't yet
 * installed. Without Echo, the views still render — they just don't
 * receive real-time push updates. The fetch-based action endpoints (sub
 * queue, pause ack) still work because they don't depend on Echo.
 */
export async function createEcho({ key, host, port, scheme }) {
    if (!key) {
        console.warn('[live-duel] Reverb key missing; skipping Echo wiring.');
        return null;
    }

    let Echo, Pusher;
    try {
        ({ default: Echo } = await import('laravel-echo'));
        ({ default: Pusher } = await import('pusher-js'));
    } catch (e) {
        console.warn('[live-duel] laravel-echo or pusher-js not installed; real-time updates disabled.', e);
        return null;
    }

    window.Pusher = Pusher;

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
