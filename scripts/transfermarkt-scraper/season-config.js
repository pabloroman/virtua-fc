// season-config.js — Maps Transfermarkt competitions to VirtuaFC's repo layout
// and serializes scrape results into canonical `data/{season}/` files.
//
// Loaded in both the popup (window) and the background service worker (self),
// so it attaches to the global object rather than using ES modules.

(function (global) {
  // Target repository and the branch new-season data branches off.
  const REPO = 'pabloroman/virtua-fc';
  const BASE_BRANCH = 'main';

  // Every competition that owns a `data/{season}/{code}/` folder, mapped to its
  // Transfermarkt competition id and how it is scraped:
  //   - 'league'      → stadiums page + per-club squads (clubs + players),
  //   - 'cup'         → participant list (id + name only),
  //   - 'continental' → participant list linking existing teams.
  // `code` is the repo folder (and DB competition id); `tmId` is the
  // Transfermarkt id embedded in the page URL and the scrape result's `id`.
  // `batch: true` means "Refresh all leagues" drives it automatically (only the
  // fully-understood stadiums-scrape leagues); cups/continental are pushed
  // one-page-at-a-time via the per-page "Push to GitHub" button.
  //
  // `expectedClubs` is set only where the engine has a *structural* requirement
  // — a Swiss league phase is four pots of nine, the Super Cup is one tie, the
  // Supercopa is a final four — and it is enforced on push (see assertClubCount).
  // Competitions whose entry list genuinely varies by season (the Copa del Rey
  // fielded 116 clubs in 2025) carry no count; the leagues are checked against
  // their schedule by `php artisan app:validate-season` instead.
  const COMPETITIONS = [
    { code: 'ESP1',    tmId: 'ES1',     name: 'LaLiga',                          kind: 'league',      batch: true },
    { code: 'ESP2',    tmId: 'ES2',     name: 'LaLiga2',                         kind: 'league',      batch: true },
    { code: 'ESP3A',   tmId: 'E3G1',    name: 'Primera Federación - Grupo I',    kind: 'league',      batch: true },
    { code: 'ESP3B',   tmId: 'E3G2',    name: 'Primera Federación - Grupo II',   kind: 'league',      batch: true },
    { code: 'ENG1',    tmId: 'GB1',     name: 'Premier League',                  kind: 'league',      batch: true },
    { code: 'DEU1',    tmId: 'L1',      name: 'Bundesliga',                      kind: 'league',      batch: true },
    { code: 'FRA1',    tmId: 'FR1',     name: 'Ligue 1',                         kind: 'league',      batch: true },
    { code: 'ITA1',    tmId: 'IT1',     name: 'Serie A',                         kind: 'league',      batch: true },
    { code: 'ESPCUP',  tmId: 'CDR',     name: 'Copa del Rey',                    kind: 'cup',         batch: false },
    { code: 'ESPSUP',  tmId: 'SUC',     name: 'Supercopa de España',            kind: 'cup',         batch: false, expectedClubs: 4 },
    { code: 'UCL',     tmId: 'CL',      name: 'UEFA Champions League',           kind: 'continental', batch: false, expectedClubs: 36 },
    { code: 'UEL',     tmId: 'EL',      name: 'UEFA Europa League',              kind: 'continental', batch: false, expectedClubs: 36 },
    { code: 'UECL',    tmId: 'UCOL',    name: 'UEFA Europa Conference League',   kind: 'continental', batch: false, expectedClubs: 36 },
    { code: 'UEFASUP', tmId: 'USC',     name: 'UEFA Super Cup',                  kind: 'continental', batch: false, expectedClubs: 2 },
  ];

  // Pool folders for single-club (squad page) pushes — these store per-team
  // {id}.json files rather than a league teams.json.
  const POOLS = ['EUR', 'INT'];

  // Transfermarkt country label -> the code the repo stores in `country`.
  // ISO 3166-1 alpha-2 throughout, except that the home nations are tracked
  // separately the way UEFA does: England is 'EN' (not 'GB') and Scotland is
  // 'GB-SCT'. Covers the UEFA membership plus the non-European countries that
  // show up in the INT pool. An unknown label yields no code at all — see
  // countryCodeFor.
  const COUNTRY_CODES = {
    // UEFA
    'Albania': 'AL', 'Andorra': 'AD', 'Armenia': 'AM', 'Austria': 'AT', 'Azerbaijan': 'AZ',
    'Belarus': 'BY', 'Belgium': 'BE', 'Bosnia-Herzegovina': 'BA', 'Bulgaria': 'BG',
    'Croatia': 'HR', 'Cyprus': 'CY', 'Czech Republic': 'CZ', 'Czechia': 'CZ',
    'Denmark': 'DK', 'England': 'EN', 'Estonia': 'EE', 'Faroe Islands': 'FO',
    'Finland': 'FI', 'France': 'FR', 'Georgia': 'GE', 'Germany': 'DE', 'Gibraltar': 'GI',
    'Greece': 'GR', 'Hungary': 'HU', 'Iceland': 'IS', 'Ireland': 'IE', 'Israel': 'IL',
    'Italy': 'IT', 'Kazakhstan': 'KZ', 'Kosovo': 'XK', 'Latvia': 'LV', 'Liechtenstein': 'LI',
    'Lithuania': 'LT', 'Luxembourg': 'LU', 'Malta': 'MT', 'Moldova': 'MD', 'Monaco': 'MC',
    'Montenegro': 'ME', 'Netherlands': 'NL', 'North Macedonia': 'MK', 'Northern Ireland': 'GB-NIR',
    'Norway': 'NO', 'Poland': 'PL', 'Portugal': 'PT', 'Romania': 'RO', 'Russia': 'RU',
    'San Marino': 'SM', 'Scotland': 'GB-SCT', 'Serbia': 'RS', 'Slovakia': 'SK',
    'Slovenia': 'SI', 'Spain': 'ES', 'Sweden': 'SE', 'Switzerland': 'CH',
    'Turkey': 'TR', 'Türkiye': 'TR', 'Ukraine': 'UA', 'Wales': 'GB-WLS',
    // Non-European clubs reachable through the INT pool
    'Algeria': 'DZ', 'Argentina': 'AR', 'Australia': 'AU', 'Brazil': 'BR', 'Canada': 'CA',
    'Chile': 'CL', 'China': 'CN', 'Colombia': 'CO', 'Ecuador': 'EC', 'Egypt': 'EG',
    'Japan': 'JP', 'Korea, South': 'KR', 'South Korea': 'KR', 'Mexico': 'MX',
    'Morocco': 'MA', 'Paraguay': 'PY', 'Peru': 'PE', 'Qatar': 'QA', 'Saudi Arabia': 'SA',
    'South Africa': 'ZA', 'Tunisia': 'TN', 'United Arab Emirates': 'AE',
    'United States': 'US', 'Uruguay': 'UY', 'Venezuela': 'VE',
  };

  // Resolve a Transfermarkt country label to a repo code, or undefined when the
  // label is unknown. Deliberately no fallback: writing a guessed code would
  // seed a wrong country silently, whereas omitting it lets
  // `php artisan app:normalize-season` backfill from the continental list and
  // `app:validate-season` flag whatever is left.
  function countryCodeFor(name) {
    if (!name) return undefined;
    const code = COUNTRY_CODES[String(name).trim()];
    if (!code) {
      console.warn(`[season-config] No country code for "${name}" — add it to COUNTRY_CODES.`);
    }
    return code;
  }

  function findByTmId(tmId) {
    if (!tmId) return null;
    const upper = String(tmId).toUpperCase();
    return COMPETITIONS.find(c => c.tmId.toUpperCase() === upper) || null;
  }

  // Resolve a club entry's transfermarkt id: leagues use `transfermarktId`,
  // cup participant lists use `id`, both may carry a crest URL.
  function resolveClubId(club) {
    if (club.transfermarktId) return String(club.transfermarktId);
    if (club.id) return String(club.id);
    const m = String(club.image || '').match(/\/(\d+)\.png$/);
    return m ? m[1] : '';
  }

  // Stable numeric sort by an extracted id.
  function byId(idOf) {
    return (a, b) => (parseInt(idOf(a), 10) || 0) - (parseInt(idOf(b), 10) || 0);
  }

  // Sort a club's players by player id, leaving every other field untouched.
  function sortClubPlayers(club) {
    if (Array.isArray(club.players)) {
      club.players = club.players.slice().sort(byId(p => p.id));
    }
    return club;
  }

  // Canonical JSON: 2-space indented with a trailing newline. Matches the repo's
  // squad-file format and the PHP `app:normalize-season` output, so the CI
  // normalize step is a no-op on what we push.
  function encode(obj) {
    return JSON.stringify(obj, null, 2) + '\n';
  }

  // Refuse to write a participant list whose size cannot be right.
  //
  // The scraper reads a page, and a page can change shape or spill clubs that
  // are not participants: the 2026 refresh pushed 37/41/41 clubs for
  // UCL/UEL/UECL (qualifying rounds harvested off the fixture page) and 7 for
  // the two-club Super Cup (sidebar links). Both sailed into a PR and one of
  // them passed `app:validate-season` outright. Failing the push is far cheaper
  // than discovering it in CI — or not discovering it.
  function assertClubCount(clubs, comp) {
    if (comp.expectedClubs === undefined) return;

    const actual = Array.isArray(clubs) ? clubs.length : 0;
    if (actual !== comp.expectedClubs) {
      throw new Error(
        `${comp.code} needs exactly ${comp.expectedClubs} clubs, this page gave ${actual}. ` +
        'Nothing was pushed — check you are on the right page for this competition.'
      );
    }
  }

  // Build a canonical teams.json string for a league/cup/continental result.
  //
  // `previousClubs` (the clubs array already on the branch) carries hand-entered
  // fields forward: UEFA seeding pots on continental lists and the `entryRound`
  // a domestic cup club joins at. The scraper cannot read either off
  // Transfermarkt, so without this a re-scrape would destroy them with no way
  // to reconstruct them. Clubs that dropped out lose theirs with them, and a
  // newly drawn club arrives without one; `app:validate-season` then reports a
  // partially potted continental file, which is the right signal after a draw
  // changes, while a cup club without an entry round simply joins at round 1.
  function toTeamsJson(result, comp, season, previousClubs) {
    const carried = new Map();
    const carriedKey = comp.kind === 'continental' ? 'pot' : (comp.kind === 'cup' ? 'entryRound' : null);
    if (carriedKey && Array.isArray(previousClubs)) {
      for (const club of previousClubs) {
        if (club && club[carriedKey] !== undefined) carried.set(resolveClubId(club), club[carriedKey]);
      }
    }

    const clubs = (result.clubs || [])
      .map(sortClubPlayers)
      .map(club => {
        const value = carried.get(resolveClubId(club));
        return value === undefined || club[carriedKey] !== undefined ? club : { ...club, [carriedKey]: value };
      })
      .sort(byId(resolveClubId));

    return encode({
      id: result.id || comp.tmId,
      name: comp.name,
      seasonID: String(season),
      clubs,
    });
  }

  // Build a canonical pool {id}.json string for a single-club squad result.
  //
  // Translates the scraper's raw `countryName` into the repo's `country` code
  // and drops the raw label. `country` lands right after `name`, matching the
  // curated pool files, so re-scrapes don't churn key order.
  function toPoolJson(result) {
    const { countryName, ...club } = result;
    const country = club.country || countryCodeFor(countryName);
    const ordered = {};

    for (const [key, value] of Object.entries(club)) {
      ordered[key] = value;
      if (key === 'name' && country) ordered.country = country;
    }
    if (country && !ordered.country) ordered.country = country;

    return encode(sortClubPlayers(ordered));
  }

  // The continental participants that need their own EUR pool file: the ones no
  // league teams.json in the same season folder already supplies a squad for.
  //
  // Mirrors `app:validate-season`'s seedable-participant rule — a participant is
  // seedable when some league or pool file in the season folder carries its
  // squad — with one deliberate difference: EUR/INT files already on the branch
  // do not count as covered. A pool file is last season's squad until this
  // season re-scrapes it, so a refresh rebuilds all of them.
  //
  // Deduplicated by club id: the UEFA Super Cup's two participants also appear
  // in the UCL and UEL lists.
  //
  // @param {Array<{code: string, clubs: Array}>} continentalLists
  // @param {Array<{clubs: Array}>} leagueLists
  // @returns {Array<{id: string, name: string, competitions: string[]}>} by id
  function poolTargets(continentalLists, leagueLists) {
    const covered = new Set();
    for (const league of leagueLists) {
      for (const club of league.clubs || []) {
        const id = resolveClubId(club);
        if (id) covered.add(id);
      }
    }

    const targets = new Map();
    for (const list of continentalLists) {
      for (const club of list.clubs || []) {
        const id = resolveClubId(club);
        if (!id || covered.has(id)) continue;

        const seen = targets.get(id);
        if (seen) {
          if (!seen.competitions.includes(list.code)) seen.competitions.push(list.code);
          continue;
        }
        targets.set(id, { id, name: club.name || id, competitions: [list.code] });
      }
    }

    return [...targets.values()].sort(byId(target => target.id));
  }

  // Map a finished scrape result to the repo file it belongs in, or null when
  // the competition is not in the registry. Throws when the participant count
  // is structurally impossible for the competition (see assertClubCount) —
  // background.js's pushScrape handler surfaces the message in the popup.
  //
  //   { path: 'data/2026/ESP1/teams.json', content: '...' }
  //
  // `opts.season` (required); for single-club pushes `opts.pool` (EUR/INT); for
  // continental lists `opts.previousClubs` (the clubs already on the branch,
  // whose pots are carried forward).
  function repoFileForResult(result, pageType, opts) {
    const season = String(opts.season);

    if (pageType === 'competition-stadiums' || pageType === 'cup-teams') {
      const comp = findByTmId(result.id);
      if (!comp) return null;
      assertClubCount(result.clubs, comp);
      return {
        path: `data/${season}/${comp.code}/teams.json`,
        content: toTeamsJson(result, comp, season, opts.previousClubs),
      };
    }

    if (pageType === 'club') {
      const pool = POOLS.includes(opts.pool) ? opts.pool : POOLS[0];
      const id = resolveClubId(result);
      if (!id) return null;
      return {
        path: `data/${season}/${pool}/${id}.json`,
        content: toPoolJson(result),
      };
    }

    return null;
  }

  global.SeasonConfig = {
    REPO,
    BASE_BRANCH,
    COMPETITIONS,
    COUNTRY_CODES,
    POOLS,
    countryCodeFor,
    findByTmId,
    resolveClubId,
    poolTargets,
    assertClubCount,
    toTeamsJson,
    toPoolJson,
    repoFileForResult,
    branchFor: season => `season-data/${season}`,
  };
})(self);
