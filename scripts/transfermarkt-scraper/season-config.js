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
  const COMPETITIONS = [
    { code: 'ESP1',    tmId: 'ES1',     name: 'LaLiga',                          kind: 'league',      batch: true },
    { code: 'ESP2',    tmId: 'ES2',     name: 'LaLiga2',                         kind: 'league',      batch: true },
    { code: 'ESP3A',   tmId: 'E3G1',    name: 'Primera Federación - Grupo I',    kind: 'league',      batch: true },
    { code: 'ESP3B',   tmId: 'E3G2',    name: 'Primera Federación - Grupo II',   kind: 'league',      batch: true },
    { code: 'ENG1',    tmId: 'ENG1',    name: 'Premier League',                  kind: 'league',      batch: true },
    { code: 'DEU1',    tmId: 'DEU1',    name: 'Bundesliga',                      kind: 'league',      batch: true },
    { code: 'FRA1',    tmId: 'FRA1',    name: 'Ligue 1',                         kind: 'league',      batch: true },
    { code: 'ITA1',    tmId: 'ITA1',    name: 'Serie A',                         kind: 'league',      batch: true },
    { code: 'ESPCUP',  tmId: 'ESPCUP',  name: 'Copa del Rey',                    kind: 'cup',         batch: false },
    { code: 'ESPSUP',  tmId: 'ESPSUP',  name: 'Supercopa de España',            kind: 'cup',         batch: false },
    { code: 'UCL',     tmId: 'UCL',     name: 'UEFA Champions League',           kind: 'continental', batch: false },
    { code: 'UEL',     tmId: 'UEL',     name: 'UEFA Europa League',              kind: 'continental', batch: false },
    { code: 'UECL',    tmId: 'UECL',    name: 'UEFA Europa Conference League',   kind: 'continental', batch: false },
    { code: 'UEFASUP', tmId: 'UEFASUP', name: 'UEFA Super Cup',                  kind: 'continental', batch: false },
  ];

  // Pool folders for single-club (squad page) pushes — these store per-team
  // {id}.json files rather than a league teams.json.
  const POOLS = ['EUR', 'INT'];

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

  // Build a canonical teams.json string for a league/cup/continental result.
  function toTeamsJson(result, comp, season) {
    const clubs = (result.clubs || [])
      .map(sortClubPlayers)
      .sort(byId(resolveClubId));
    return encode({
      id: result.id || comp.tmId,
      name: comp.name,
      seasonID: String(season),
      clubs,
    });
  }

  // Build a canonical pool {id}.json string for a single-club squad result.
  function toPoolJson(result) {
    return encode(sortClubPlayers({ ...result }));
  }

  // Map a finished scrape result to the repo file it belongs in, or null when
  // the competition is not in the registry.
  //
  //   { path: 'data/2026/ESP1/teams.json', content: '...' }
  //
  // `opts.season` (required) and, for single-club pushes, `opts.pool` (EUR/INT).
  function repoFileForResult(result, pageType, opts) {
    const season = String(opts.season);

    if (pageType === 'competition-stadiums' || pageType === 'cup-teams') {
      const comp = findByTmId(result.id);
      if (!comp) return null;
      return {
        path: `data/${season}/${comp.code}/teams.json`,
        content: toTeamsJson(result, comp, season),
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
    POOLS,
    findByTmId,
    toTeamsJson,
    toPoolJson,
    repoFileForResult,
    branchFor: season => `season-data/${season}`,
  };
})(self);
