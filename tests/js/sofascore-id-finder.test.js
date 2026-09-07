/**
 * Tests for the Sofascore player-ID finder's matching and output.
 *
 * The regression these guard: a confirmed match writes a live CSV row into
 * data/sofascore_ids_overrides.csv, and a wrong Sofascore id shows the wrong
 * face on a player's profile for good. Football is full of same-name players,
 * so the rule is that ONLY an exact date-of-birth match may ever produce a row —
 * everything else has to come out commented, for a human.
 *
 * find-ids.js is a console script, not a module: it is an IIFE that builds a
 * panel on load, so it is run in a vm context with a stub DOM and a stub fetch,
 * then driven through the namespace object it leaves on `window`.
 */
import { describe, it, expect } from 'vitest';
import fs from 'fs';
import path from 'path';
import vm from 'vm';

const SOURCE = path.resolve(__dirname, '../../scripts/sofascore-id-finder/find-ids.js');

const dobTs = (iso) => {
    const [y, m, d] = iso.split('-').map(Number);
    return Date.UTC(y, m - 1, d) / 1000;
};

function makeEl() {
    return {
        style: {}, value: '', textContent: '', disabled: false,
        scrollTop: 0, scrollHeight: 0, href: '', download: '',
        addEventListener() {}, click() {}, remove() {}, appendChild() {},
    };
}

/**
 * @param players  fixtures keyed by search query -> [{id, name}]
 * @param details  fixtures keyed by player id -> {name, dob, team}
 */
function load({ search = {}, details = {} } = {}) {
    const roles = {};
    for (const r of ['players', 'concurrency', 'delay', 'go', 'bar', 'fill', 'log', 'close']) {
        roles[r] = makeEl();
    }
    const root = { ...makeEl(), querySelector: (sel) => roles[sel.match(/"(.+)"/)[1]] };

    const downloads = [];
    const ctx = {
        console: { log() {}, warn() {}, error() {} },
        setTimeout, clearTimeout, Math, Promise, Object, Error, JSON, Date, Array, String, Number,
        URLSearchParams, TextEncoder, RegExp, parseInt, isNaN,
        Blob: class { constructor(parts) { this.parts = parts; } },
        URL: { createObjectURL: () => 'blob:stub', revokeObjectURL() {} },
        document: {
            createElement: (tag) => (tag === 'div' ? root : makeEl()),
            body: { appendChild() {} },
        },
        async fetch(url) {
            if (url.startsWith('/api/v1/search/all')) {
                const q = new URLSearchParams(url.split('?')[1]).get('q');
                const hits = search[q] || [];
                return {
                    ok: true, status: 200,
                    json: async () => ({ results: hits.map((h) => ({ type: 'player', entity: h })) }),
                };
            }
            const id = url.split('/').pop();
            const d = details[id];
            if (!d) return { ok: false, status: 404, json: async () => null };
            return {
                ok: true, status: 200,
                json: async () => ({ player: { name: d.name, dateOfBirthTimestamp: dobTs(d.dob), team: { name: d.team } } }),
            };
        },
    };
    ctx.window = ctx;
    ctx.globalThis = ctx;
    vm.createContext(ctx);
    vm.runInContext(fs.readFileSync(SOURCE, 'utf8'), ctx);

    return {
        roles,
        downloads,
        ns: () => ctx.window.__sofascoreIdFinder,
        async run(input) {
            roles.players.value = JSON.stringify(input);
            roles.delay.value = '0';
            roles.concurrency.value = '2';
            await ctx.window.__sofascoreIdFinder.run();
            return ctx.window.__sofascoreIdFinder.lastCsv;
        },
    };
}

// Rows that are not comments and not the header.
const dataRows = (csv) => csv.split('\n').filter((l) => l && !l.startsWith('#') && !l.startsWith('key_'));

describe('sofascore id finder', () => {
    it('writes a row when the date of birth matches exactly', async () => {
        const h = load({
            search: { 'Joshua Kimmich': [{ id: 111, name: 'Joshua Kimmich' }] },
            details: { 111: { name: 'Joshua Kimmich', dob: '1995-02-08', team: 'Bayern' } },
        });

        const csv = await h.run([{ tm: '161056', name: 'Joshua Kimmich', dob: '1995-02-08', club: 'Bayern Munich' }]);

        expect(dataRows(csv)).toEqual(['161056,111,Joshua Kimmich']);
    });

    it('refuses a same-name player whose date of birth differs', async () => {
        const h = load({
            search: { 'Dani Lopez': [{ id: 222, name: 'Dani Lopez' }] },
            details: { 222: { name: 'Dani Lopez', dob: '1990-01-01', team: 'Elsewhere' } },
        });

        const csv = await h.run([{ tm: '999', name: 'Dani Lopez', dob: '2004-06-15', club: 'Real Jaén' }]);

        expect(dataRows(csv)).toEqual([]);
        expect(csv).toContain('# 999,,Dani Lopez');
        expect(csv).toContain('none with matching DOB');
    });

    it('keeps checking candidates until one confirms', async () => {
        const h = load({
            search: { 'Diop': [{ id: 1, name: 'Issa Diop' }, { id: 2, name: 'Pape Diop' }, { id: 3, name: 'Edan Diop' }] },
            details: {
                1: { name: 'Issa Diop', dob: '1997-01-09', team: 'Ipswich' },
                2: { name: 'Pape Diop', dob: '2003-09-04', team: 'Strasbourg' },
                3: { name: 'Edan Diop', dob: '2004-08-28', team: 'Monaco' },
            },
        });

        const csv = await h.run([{ tm: '77', name: 'Diop', dob: '2004-08-28', club: 'AS Monaco' }]);

        expect(dataRows(csv)).toEqual(['77,3,Edan Diop']);
    });

    it('retries with accents stripped, because Sofascore spells them inconsistently', async () => {
        const h = load({
            // Only the accent-free spelling returns anything.
            search: { 'Lacine Kone': [{ id: 444, name: 'Lacine Kone' }] },
            details: { 444: { name: 'Lacine Kone', dob: '2002-11-01', team: 'Mérida AD' } },
        });

        const csv = await h.run([{ tm: '1078984', name: 'Lacine Koné', dob: '2002-11-01', club: 'Mérida AD' }]);

        expect(dataRows(csv)).toEqual(['1078984,444,Lacine Kone']);
    });

    it('reports a player Sofascore has never heard of', async () => {
        const h = load({ search: {}, details: {} });

        const csv = await h.run([{ tm: '5', name: 'Nobody At All', dob: '2009-08-08', club: 'Girona FC' }]);

        expect(dataRows(csv)).toEqual([]);
        expect(csv).toContain('no search hits');
    });

    it('quotes a name containing a comma so the CSV stays parseable', async () => {
        const h = load({
            search: { 'Silva, Jr.': [{ id: 8, name: 'Silva, Jr.' }] },
            details: { 8: { name: 'Silva, Jr.', dob: '2001-03-03', team: 'Porto' } },
        });

        const csv = await h.run([{ tm: '12', name: 'Silva, Jr.', dob: '2001-03-03', club: 'FC Porto' }]);

        expect(dataRows(csv)).toEqual(['12,8,"Silva, Jr."']);
    });

    it('skips entries with no usable date of birth rather than guessing', async () => {
        const h = load({
            search: { 'Has Dob': [{ id: 9, name: 'Has Dob' }] },
            details: { 9: { name: 'Has Dob', dob: '2000-05-05', team: 'X' } },
        });

        const csv = await h.run([
            { tm: '1', name: 'No Dob', dob: '', club: 'X' },
            { tm: '2', name: 'Has Dob', dob: '2000-05-05', club: 'X' },
        ]);

        expect(dataRows(csv)).toEqual(['2,9,Has Dob']);
        expect(csv).not.toContain('No Dob');
    });

    it('emits a header the overrides file can absorb directly', async () => {
        const h = load({
            search: { A: [{ id: 1, name: 'A' }] },
            details: { 1: { name: 'A', dob: '2000-01-01', team: 'T' } },
        });

        const csv = await h.run([{ tm: '3', name: 'A', dob: '2000-01-01', club: 'T' }]);

        expect(csv.split('\n')[0]).toBe('key_transfermarkt,key_sofascore,name');
    });
});
