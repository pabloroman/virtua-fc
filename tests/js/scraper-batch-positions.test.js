/**
 * Tests for the player-positions batch loop in the Transfermarkt scraper's
 * background service worker.
 *
 * The regression these guard: a player the server refused (HTTP 403) must never
 * be recorded. `positions: []` is how the scraper says "checked, has no
 * secondary position", so storing it for a blocked page would bake a player in
 * as having none — permanently, since the run resumes by skipping anything
 * already recorded.
 *
 * background.js is a service worker, not a module: it is loaded into a vm
 * context with a stub `chrome` so the loop can be driven directly.
 */
import { describe, it, expect, beforeEach } from 'vitest';
import fs from 'fs';
import path from 'path';
import vm from 'vm';

const SOURCE = path.resolve(__dirname, '../../scripts/transfermarkt-scraper/background.js');

// Top-level `class` / `const` are lexically scoped to the script, so they are
// not reachable on the context object without re-exporting them.
const EXPORTS = '\n;globalThis.__BlockedError = BlockedError; globalThis.__PACING = BATCH_PACING;';

function loadWorker() {
    const store = {};
    const messageListeners = [];

    // pacedSleep waits against the wall clock, so a stubbed sleep has to move
    // that clock or the slice loop never reaches its deadline.
    let clock = 1700000000000;
    class FakeDate extends Date {
        static now() {
            return clock;
        }
    }

    const ctx = {
        console: { log() {}, warn() {}, error() {} },
        setTimeout, clearTimeout, Math, Promise, Object, Error, JSON, Date: FakeDate,
        importScripts: () => {},
        chrome: {
            webRequest: { onCompleted: { addListener: () => {} } },
            tabs: {
                onUpdated: { addListener: () => {}, removeListener: () => {} },
                update: () => {},
            },
            storage: {
                local: {
                    get: async () => ({ batchPositions: store.batchPositions }),
                    set: async (o) => Object.assign(store, o),
                    remove: () => {},
                },
            },
            runtime: {
                // Captured so a test can drive Stop the way the popup does —
                // batchAborted is script-local, not reachable from outside.
                onMessage: { addListener: (fn) => { messageListeners.push(fn); } },
                sendMessage: async () => {},
                lastError: null,
            },
            scripting: { executeScript: async () => [{ result: null }] },
            action: { setBadgeText: () => {}, setBadgeBackgroundColor: () => {} },
        },
    };
    ctx.self = ctx;
    ctx.globalThis = ctx;
    vm.createContext(ctx);
    vm.runInContext(fs.readFileSync(SOURCE, 'utf8') + EXPORTS, ctx);

    // Long waits are served in keepalive slices, so the raw sleeps are not the
    // waits we care about. Each page attempt pushes a marker, letting a test
    // group the slices back into "how long did it wait before retry N".
    const slices = [];
    ctx.sleep = (ms) => { slices.push(ms); clock += ms; return Promise.resolve(); };
    ctx.updateProgress = async () => {};
    ctx.scrapePlayerPositionsFromTab = async () => ({ positions: ['Central Midfield'] });

    store.batchPositions = {};

    const markAttempt = () => slices.push('ATTEMPT');

    /** Total slept between consecutive page attempts. */
    const waitsBetweenAttempts = () => {
        const totals = [];
        let current = null;
        for (const entry of slices) {
            if (entry === 'ATTEMPT') {
                if (current !== null) totals.push(current);
                current = 0;
            } else if (current !== null) {
                current += entry;
            }
        }
        if (current) totals.push(current);

        return totals;
    };

    const stop = () => messageListeners.forEach(fn => fn({ action: 'stopBatch' }, {}, () => {}));

    return { ctx, store, slices, markAttempt, waitsBetweenAttempts, stop };
}

describe('scrapeBatchPlayerPositions', () => {
    let worker;

    beforeEach(() => {
        worker = loadWorker();
    });

    it('records a player scraped without incident', async () => {
        const { ctx, store, markAttempt } = worker;
        ctx.navigateAndWait = async () => { markAttempt(); };

        await ctx.scrapeBatchPlayerPositions(1, ['1', '2']);

        expect(store.batchPositions).toEqual({
            1: ['Central Midfield'],
            2: ['Central Midfield'],
        });
    });

    it('waits and retries on a 403, then records the recovered player', async () => {
        const { ctx, store, markAttempt, waitsBetweenAttempts } = worker;
        const Blocked = ctx.__BlockedError;
        let attempts = 0;
        ctx.navigateAndWait = async () => {
            markAttempt();
            if (attempts++ < 2) throw new Blocked(403);
        };

        await ctx.scrapeBatchPlayerPositions(1, ['111']);

        expect(attempts).toBe(3);
        expect(store.batchPositions).toEqual({ 111: ['Central Midfield'] });

        const [first, second] = ctx.__PACING.blockCooldownsMs;
        const waited = waitsBetweenAttempts();
        expect(waited[0]).toBeGreaterThanOrEqual(first);
        expect(waited[1]).toBeGreaterThanOrEqual(second);
    });

    it('escalates through the whole cooldown ladder before giving up', async () => {
        const { ctx, markAttempt, waitsBetweenAttempts } = worker;
        const Blocked = ctx.__BlockedError;
        ctx.navigateAndWait = async () => { markAttempt(); throw new Blocked(403); };

        await ctx.scrapeBatchPlayerPositions(1, ['222']);

        const ladder = ctx.__PACING.blockCooldownsMs;
        const waited = waitsBetweenAttempts();
        expect(waited).toHaveLength(ladder.length);
        ladder.forEach((rung, i) => expect(waited[i]).toBeGreaterThanOrEqual(rung));
        for (let i = 1; i < waited.length; i++) {
            expect(waited[i]).toBeGreaterThan(waited[i - 1]);
        }
    });

    it('leaves a blocked player unrecorded so Resume retries him', async () => {
        const { ctx, store, markAttempt } = worker;
        const Blocked = ctx.__BlockedError;
        ctx.navigateAndWait = async () => { markAttempt(); throw new Blocked(403); };

        await ctx.scrapeBatchPlayerPositions(1, ['222']);

        expect(store.batchPositions).toEqual({});
        expect(store.batchPositions).not.toHaveProperty('222');
    });

    it('stops the run once too many players fail in a row', async () => {
        const { ctx, store, markAttempt } = worker;
        const Blocked = ctx.__BlockedError;
        ctx.navigateAndWait = async () => { markAttempt(); throw new Blocked(403); };
        const ids = Array.from({ length: 10 }, (_, i) => String(i + 1));

        await ctx.scrapeBatchPlayerPositions(1, ids);

        expect(Object.keys(store.batchPositions)).toHaveLength(0);
    });

    it('gives an ordinary error one flat backoff, not the ladder', async () => {
        const { ctx, store, markAttempt, waitsBetweenAttempts } = worker;
        ctx.navigateAndWait = async () => { markAttempt(); throw new Error('Navigation timeout'); };

        await ctx.scrapeBatchPlayerPositions(1, ['333']);

        expect(waitsBetweenAttempts()).toEqual([ctx.__PACING.retryBackoffMs]);
        expect(store.batchPositions).toEqual({});
    });

    it('never sleeps longer than the MV3 idle timeout in one go', async () => {
        const { ctx, slices, markAttempt } = worker;
        const Blocked = ctx.__BlockedError;
        ctx.navigateAndWait = async () => { markAttempt(); throw new Blocked(403); };

        // The 300s rung would kill the service worker if it were one setTimeout.
        await ctx.scrapeBatchPlayerPositions(1, ['222']);

        const longest = Math.max(...slices.filter(s => typeof s === 'number'));
        expect(longest).toBeLessThan(30000);
    });

    it('stops promptly when Stop is pressed mid-cooldown', async () => {
        const { ctx, store, markAttempt, stop } = worker;
        const Blocked = ctx.__BlockedError;
        let attempts = 0;
        ctx.navigateAndWait = async () => {
            markAttempt();
            attempts++;
            stop(); // as the popup's Stop button does
            throw new Blocked(403);
        };

        await ctx.scrapeBatchPlayerPositions(1, ['444', '555']);

        // The abort is noticed inside the cooldown slices rather than after the
        // full rung, so neither a retry nor the next player is started.
        expect(attempts).toBe(1);
        expect(store.batchPositions).toEqual({});
    });
});
