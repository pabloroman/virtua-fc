// content.js — Extracts competition + club list from a Transfermarkt league page.
//
// Output format:
// {
//   "id": "ES1",
//   "name": "LaLiga",
//   "seasonID": "2024",
//   "clubs": [ { "id": "131", "name": "FC Barcelona" }, ... ]
// }

(function () {
  const url = window.location.href;

  // ---------------------------------------------------------------------------
  // 1. Extract competition ID from the URL
  //    e.g. /laliga/startseite/wettbewerb/ES1
  // ---------------------------------------------------------------------------
  const compIdMatch = url.match(/\/wettbewerb\/([A-Z0-9]+)/i);
  const competitionId = compIdMatch ? compIdMatch[1] : '';

  // ---------------------------------------------------------------------------
  // 2. Extract season ID — URL param, path segment, or season dropdown
  // ---------------------------------------------------------------------------
  let seasonId = '';
  const seasonUrlMatch = url.match(/saison_id[=/](\d{4})/);
  if (seasonUrlMatch) {
    seasonId = seasonUrlMatch[1];
  } else {
    const seasonSelect = document.querySelector('select[name="saison_id"]');
    if (seasonSelect) {
      seasonId = seasonSelect.value;
    } else {
      // Fallback: look for selected season text anywhere on page
      const seasonEl = document.querySelector('.chzn-single span, .inline-select option[selected]');
      if (seasonEl) {
        const m = seasonEl.textContent.match(/(\d{4})/);
        if (m) seasonId = m[1];
      }
    }
  }

  // ---------------------------------------------------------------------------
  // 3. Extract competition name from the page header
  // ---------------------------------------------------------------------------
  let competitionName = '';
  const headerEl =
    document.querySelector('h1.data-header__headline') ||
    document.querySelector('.data-header__headline-wrapper h1') ||
    document.querySelector('header h1') ||
    document.querySelector('h1');
  if (headerEl) {
    competitionName = headerEl.textContent.replace(/\s+/g, ' ').trim();
  }

  // ---------------------------------------------------------------------------
  // 4. Extract clubs
  //    Club links contain "/verein/<id>" in their href.
  //    We deduplicate by ID and pick the link with actual name text.
  // ---------------------------------------------------------------------------
  const clubs = [];
  const seenIds = new Set();

  const rows = document.querySelectorAll('.responsive-table tbody tr, table tbody tr');

  rows.forEach(tr => {
    if (tr.querySelector('th')) return;

    const links = tr.querySelectorAll('a[href*="/verein/"]');
    links.forEach(a => {
      const href = a.getAttribute('href') || '';
      const idMatch = href.match(/\/verein\/(\d+)/);
      if (!idMatch) return;

      const clubId = idMatch[1];
      if (seenIds.has(clubId)) return;

      // Prefer visible text, then img alt, then title attribute
      let clubName = a.textContent.replace(/\s+/g, ' ').trim();
      if (!clubName) {
        const img = a.querySelector('img');
        if (img) clubName = (img.alt || img.title || '').trim();
      }
      if (!clubName) clubName = (a.title || '').trim();

      // Skip logo-only links with no real name
      if (!clubName || clubName.length < 2) return;

      seenIds.add(clubId);
      clubs.push({ id: clubId, name: clubName });
    });
  });

  return {
    id: competitionId,
    name: competitionName,
    seasonID: seasonId,
    clubs
  };
})();
