/**
 * localStorage persistence for revealed-event state so page refreshes mid-
 * match can restore the exact event feed the user saw during live play.
 * Keys are scoped by matchId to avoid cross-match contamination.
 */

const STORAGE_KEYS = Object.freeze({
    events: (matchId) => `live_match_events:${matchId}`,
});

export function createEventCache({ matchId, storage = globalThis.localStorage }) {
    const enabled = !!matchId && !!storage;
    const key = enabled ? STORAGE_KEYS.events(matchId) : null;

    return {
        save(payload) {
            if (!enabled) return;
            try {
                storage.setItem(key, JSON.stringify(payload));
            } catch (_) { /* quota exceeded — silently skip */ }
        },

        /**
         * Read and return the cached payload, or null when empty / invalid.
         * Callers decide how to apply the fields.
         */
        restore() {
            if (!enabled) return null;
            try {
                const raw = storage.getItem(key);
                if (!raw) return null;
                return JSON.parse(raw);
            } catch (_) { return null; }
        },

        clear() {
            if (!enabled) return;
            storage.removeItem(key);
        },
    };
}
