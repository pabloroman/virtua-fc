/**
 * Build an Echo instance configured for Laravel Reverb.
 *
 * The package imports are routed through a variable expression + a
 * /* @vite-ignore *​/ comment so Vite's dep-optimizer doesn't try to
 * pre-bundle laravel-echo / pusher-js at module-load time. Without that,
 * a fresh checkout (where npm install hasn't been run for these new deps
 * yet) crashes the whole live-duel module chain and Alpine never sees
 * the liveDuel factory.
 *
 * Returns null when:
 *  - the Reverb key isn't configured (no BROADCAST_CONNECTION=reverb), or
 *  - laravel-echo / pusher-js aren't installed.
 * In both cases the rest of the page still renders — the user just
 * doesn't get real-time push updates. Fetch-based action endpoints
 * (queue-sub, ack-pause) keep working.
 */
export async function createEcho({ key, host, port, scheme }) {
    if (!key) {
        console.warn('[live-duel] Reverb key missing; skipping Echo wiring.');
        return null;
    }

    let Echo, Pusher;
    try {
        const echoPkg = 'laravel-echo';
        const pusherPkg = 'pusher-js';
        ({ default: Echo } = await import(/* @vite-ignore */ echoPkg));
        ({ default: Pusher } = await import(/* @vite-ignore */ pusherPkg));
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
