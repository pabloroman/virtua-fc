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
    const ctx = {
        console: { log() {}, warn() {}, error() {} },
        setTimeout, clearTimeout, Math, Promise, Object, Error, JSON, Date,
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
            runtime: { onMessage: { addListener: () => {} }, sendMessage: async () => {}, lastError: null },
            scripting: { executeScript: async () => [{ result: null }] },
            action: { setBadgeText: () => {}, setBadgeBackgroundColor: () => {} },
        },
    };
    ctx.self = ctx;
    ctx.globalThis = ctx;
    vm.createContext(ctx);
    vm.runInContext(fs.readFileSync(SOURCE, 'utf8') + EXPORTS, ctx);

    const waits = [];
    ctx.sleep = (ms) => { waits.push(ms); return Promise.resolve(); };
    ctx.updateProgress = async () => {};
    ctx.scrapePlayerPositionsFromTab = async () => ({ positions: ['Central Midfield'] });

    store.batchPositions = {};

    return { ctx, store, waits };
}

describe('scrapeBatchPlayerPositions', () => {
    let worker;

    beforeEach(() => {
        worker = loadWorker();
    });

    it('records a player scraped without incident', async () => {
        const { ctx, store } = worker;
        ctx.navigateAndWait = async () => {};

        await ctx.scrapeBatchPlayerPositions(1, ['1', '2']);

        expect(store.batchPositions).toEqual({
            1: ['Central Midfield'],
            2: ['Central Midfield'],
        });
    });

    it('waits and retries on a 403, then records the recovered player', async () => {
        const { ctx, store, waits } = worker;
        const Blocked = ctx.__BlockedError;
        let attempts = 0;
        ctx.navigateAndWait = async () => {
            if (attempts++ < 2) throw new Blocked(403);
        };

        await ctx.scrapeBatchPlayerPositions(1, ['111']);

        expect(attempts).toBe(3);
        expect(store.batchPositions).toEqual({ 111: ['Central Midfield'] });
        // First two rungs of the cooldown ladder, plus up to blockJitterMs.
        const [first, second] = ctx.__PACING.blockCooldownsMs;
        expect(waits[0]).toBeGreaterThanOrEqual(first);
        expect(waits[1]).toBeGreaterThanOrEqual(second);
    });

    it('escalates through the whole cooldown ladder before giving up', async () => {
        const { ctx, waits } = worker;
        const Blocked = ctx.__BlockedError;
        ctx.navigateAndWait = async () => { throw new Blocked(403); };

        await ctx.scrapeBatchPlayerPositions(1, ['222']);

        const ladder = ctx.__PACING.blockCooldownsMs;
        expect(waits).toHaveLength(ladder.length);
        ladder.forEach((rung, i) => expect(waits[i]).toBeGreaterThanOrEqual(rung));
        // Strictly increasing — a short throttle costs seconds, a ban escalates.
        for (let i = 1; i < waits.length; i++) {
            expect(waits[i]).toBeGreaterThan(waits[i - 1]);
        }
    });

    it('leaves a blocked player unrecorded so Resume retries him', async () => {
        const { ctx, store } = worker;
        const Blocked = ctx.__BlockedError;
        ctx.navigateAndWait = async () => { throw new Blocked(403); };

        await ctx.scrapeBatchPlayerPositions(1, ['222']);

        expect(store.batchPositions).toEqual({});
        expect(store.batchPositions).not.toHaveProperty('222');
    });

    it('stops the run once too many players fail in a row', async () => {
        const { ctx, store } = worker;
        const Blocked = ctx.__BlockedError;
        ctx.navigateAndWait = async () => { throw new Blocked(403); };
        const ids = Array.from({ length: 10 }, (_, i) => String(i + 1));

        await ctx.scrapeBatchPlayerPositions(1, ids);

        // Bailed out rather than grinding through all ten.
        expect(Object.keys(store.batchPositions)).toHaveLength(0);
    });

    it('gives an ordinary error one flat backoff, not the ladder', async () => {
        const { ctx, store, waits } = worker;
        ctx.navigateAndWait = async () => { throw new Error('Navigation timeout'); };

        await ctx.scrapeBatchPlayerPositions(1, ['333']);

        expect(waits).toEqual([ctx.__PACING.retryBackoffMs]);
        expect(store.batchPositions).toEqual({});
    });
});
