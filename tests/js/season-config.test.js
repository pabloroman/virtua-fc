/**
 * Tests for the Transfermarkt scraper's season-config.js — the layer that turns
 * a raw scrape into the exact JSON that lands in `data/{season}/`.
 *
 * Two regressions matter here. UEFA seeding pots are entered by hand (they are
 * not on any page the scraper reads), so a continental re-scrape must carry
 * them forward instead of overwriting them. And a club's country must never be
 * guessed: a wrong code seeds a wrong country silently, whereas a missing one
 * gets backfilled by `app:normalize-season` and flagged by `app:validate-season`.
 */
import { describe, it, expect, beforeAll, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

let SeasonConfig;

beforeAll(() => {
    // season-config.js is a plain script that attaches to the global object
    // (it is loaded by both the popup and the MV3 service worker, not imported).
    globalThis.self = globalThis;
    vi.spyOn(console, 'warn').mockImplementation(() => {});
    const src = readFileSync(
        resolve(__dirname, '../../scripts/transfermarkt-scraper/season-config.js'),
        'utf8',
    );
    // eslint-disable-next-line no-new-func
    new Function(src)();
    SeasonConfig = globalThis.SeasonConfig;
});

const UCL = { code: 'UCL', tmId: 'CL', name: 'UEFA Champions League', kind: 'continental' };
const ESPCUP = { code: 'ESPCUP', tmId: 'CDR', name: 'Copa del Rey', kind: 'cup' };

const parse = json => JSON.parse(json);

describe('toTeamsJson — pot preservation on continental re-scrapes', () => {
    const scraped = { id: 'CL', clubs: [{ id: '11', name: 'Arsenal' }, { id: '418', name: 'Real Madrid' }] };
    const previous = [{ id: '11', name: 'Arsenal', pot: 2 }, { id: '418', name: 'Real Madrid', pot: 1 }];

    it('carries pots forward by transfermarkt id', () => {
        const clubs = parse(SeasonConfig.toTeamsJson(scraped, UCL, '2026', previous)).clubs;

        expect(clubs.find(c => c.id === '11').pot).toBe(2);
        expect(clubs.find(c => c.id === '418').pot).toBe(1);
    });

    it('leaves a newly drawn club without a pot rather than inventing one', () => {
        const withNewcomer = { id: 'CL', clubs: [...scraped.clubs, { id: '62', name: 'SK Slavia Prague' }] };
        const clubs = parse(SeasonConfig.toTeamsJson(withNewcomer, UCL, '2026', previous)).clubs;

        expect(clubs.find(c => c.id === '62')).not.toHaveProperty('pot');
    });

    it('drops the pot of a club that is no longer a participant', () => {
        const clubs = parse(SeasonConfig.toTeamsJson(
            { id: 'CL', clubs: [{ id: '11', name: 'Arsenal' }] },
            UCL,
            '2026',
            previous,
        )).clubs;

        expect(clubs).toHaveLength(1);
        expect(clubs.map(c => c.id)).not.toContain('418');
    });

    it('prefers a freshly scraped pot over the stored one', () => {
        const repotted = { id: 'CL', clubs: [{ id: '11', name: 'Arsenal', pot: 4 }] };
        const clubs = parse(SeasonConfig.toTeamsJson(repotted, UCL, '2026', previous)).clubs;

        expect(clubs[0].pot).toBe(4);
    });

    it('ignores previous clubs for a non-continental competition', () => {
        const clubs = parse(SeasonConfig.toTeamsJson(
            { id: 'CDR', clubs: [{ id: '11', name: 'Arsenal' }] },
            ESPCUP,
            '2026',
            previous,
        )).clubs;

        expect(clubs[0]).not.toHaveProperty('pot');
    });

    it('is unchanged when there is nothing stored yet', () => {
        expect(SeasonConfig.toTeamsJson(scraped, UCL, '2026', null))
            .toBe(SeasonConfig.toTeamsJson(scraped, UCL, '2026'));
    });
});

describe('countryCodeFor', () => {
    it('maps the home nations the way the repo tracks them, not ISO', () => {
        expect(SeasonConfig.countryCodeFor('England')).toBe('EN');
        expect(SeasonConfig.countryCodeFor('Scotland')).toBe('GB-SCT');
    });

    it('maps ordinary UEFA members to ISO alpha-2', () => {
        expect(SeasonConfig.countryCodeFor('Germany')).toBe('DE');
        expect(SeasonConfig.countryCodeFor('Netherlands')).toBe('NL');
        expect(SeasonConfig.countryCodeFor('North Macedonia')).toBe('MK');
        expect(SeasonConfig.countryCodeFor('Kosovo')).toBe('XK');
    });

    it('accepts both spellings Transfermarkt uses for Türkiye', () => {
        expect(SeasonConfig.countryCodeFor('Türkiye')).toBe('TR');
        expect(SeasonConfig.countryCodeFor('Turkey')).toBe('TR');
    });

    it('returns nothing for an unknown label instead of guessing', () => {
        expect(SeasonConfig.countryCodeFor('Atlantis')).toBeUndefined();
        expect(SeasonConfig.countryCodeFor(null)).toBeUndefined();
    });
});

describe('toPoolJson', () => {
    const club = {
        transfermarktId: '1090',
        name: 'AZ Alkmaar',
        countryName: 'Netherlands',
        image: 'https://tmssl.akamaized.net/images/wappen/big/1090.png',
        players: [{ id: '20' }, { id: '3' }],
    };

    it('translates the scraped country label into a code and drops the label', () => {
        const out = parse(SeasonConfig.toPoolJson(club));

        expect(out.country).toBe('NL');
        expect(out).not.toHaveProperty('countryName');
    });

    it('places country immediately after name, as the curated pool files do', () => {
        const keys = Object.keys(parse(SeasonConfig.toPoolJson(club)));

        expect(keys[keys.indexOf('name') + 1]).toBe('country');
    });

    it('omits country entirely when the label is unknown', () => {
        const out = parse(SeasonConfig.toPoolJson({ ...club, countryName: 'Atlantis' }));

        expect(out).not.toHaveProperty('country');
        expect(out).not.toHaveProperty('countryName');
    });

    it('still sorts players by id', () => {
        expect(parse(SeasonConfig.toPoolJson(club)).players.map(p => p.id)).toEqual(['3', '20']);
    });
});

describe('repoFileForResult', () => {
    it('routes a continental scrape to its teams.json and forwards stored pots', () => {
        const file = SeasonConfig.repoFileForResult(
            { id: 'CL', clubs: [{ id: '11', name: 'Arsenal' }] },
            'cup-teams',
            { season: '2026', previousClubs: [{ id: '11', pot: 2 }] },
        );

        expect(file.path).toBe('data/2026/UCL/teams.json');
        expect(parse(file.content).clubs[0].pot).toBe(2);
    });

    it('routes a single club squad into the chosen pool', () => {
        const file = SeasonConfig.repoFileForResult(
            { transfermarktId: '1090', name: 'AZ Alkmaar', countryName: 'Netherlands' },
            'club',
            { season: '2026', pool: 'EUR' },
        );

        expect(file.path).toBe('data/2026/EUR/1090.json');
        expect(parse(file.content).country).toBe('NL');
    });
});

/**
 * poolTargets decides which continental participants get scraped into EUR/.
 *
 * Both directions cost real work if wrong: a club wrongly included is a
 * redundant pool file (and a needless page load in a run that already takes
 * minutes), while a club wrongly excluded has no squad at all — the seeder drops
 * it, SeasonInitializationService then skips the whole league phase, and the
 * competition ends up with no fixtures and nothing said about it in-game.
 */
describe('poolTargets — clubs that still need an EUR pool file', () => {
    const league = clubs => ({ code: 'ESP1', clubs });
    const ucl = clubs => ({ code: 'UCL', clubs });

    it('keeps participants no league covers', () => {
        const targets = SeasonConfig.poolTargets(
            [ucl([{ id: '418', name: 'Real Madrid' }, { id: '10482', name: 'Kairat Almaty' }])],
            [league([{ transfermarktId: '418', name: 'Real Madrid' }])],
        );

        expect(targets).toEqual([{ id: '10482', name: 'Kairat Almaty', competitions: ['UCL'] }]);
    });

    it('matches league clubs on transfermarktId, which is the key league files use', () => {
        // Continental lists carry `id`; league teams.json carries
        // `transfermarktId`. Comparing the raw keys would cover nothing.
        const targets = SeasonConfig.poolTargets(
            [ucl([{ id: '11', name: 'Arsenal' }])],
            [league([{ transfermarktId: '11', name: 'Arsenal' }])],
        );

        expect(targets).toEqual([]);
    });

    it('falls back to the crest url for a club with no id fields', () => {
        const targets = SeasonConfig.poolTargets(
            [ucl([{ id: '11', name: 'Arsenal' }])],
            [league([{ name: 'Arsenal', image: 'https://tmssl.akamaized.net/images/wappen/big/11.png' }])],
        );

        expect(targets).toEqual([]);
    });

    it('lists a club once, naming every competition it plays in', () => {
        // The Super Cup's two participants are also in the UCL and UEL lists;
        // scraping the same squad twice would push two identical files.
        const targets = SeasonConfig.poolTargets(
            [
                ucl([{ id: '10482', name: 'Kairat Almaty' }]),
                { code: 'UEFASUP', clubs: [{ id: '10482', name: 'Kairat Almaty' }] },
            ],
            [],
        );

        expect(targets).toEqual([
            { id: '10482', name: 'Kairat Almaty', competitions: ['UCL', 'UEFASUP'] },
        ]);
    });

    it('orders by club id so a re-run walks the same sequence', () => {
        const targets = SeasonConfig.poolTargets(
            [ucl([{ id: '10482', name: 'Kairat' }, { id: '294', name: 'Slavia' }, { id: '1090', name: 'Pafos' }])],
            [],
        );

        expect(targets.map(t => t.id)).toEqual(['294', '1090', '10482']);
    });

    it('skips entries with no resolvable id rather than scraping a bad url', () => {
        const targets = SeasonConfig.poolTargets([ucl([{ name: 'Mystery FC' }])], []);

        expect(targets).toEqual([]);
    });
});
