// content-cup-teams.js — Extracts the participating clubs of a cup / continental
// competition from Transfermarkt.
//
// Two page shapes, because "who is in this competition" means different things
// for a Swiss league phase than for a knockout bracket:
//
//   Swiss (UCL/UEL/UECL) → the league-phase table
//     https://www.transfermarkt.com/{slug}/tabelle/pokalwettbewerb/{id}/saison_id/{year}
//     One row per participant, exactly 36 of them, and qualifying rounds are not
//     on the page at all.
//
//   Knockout (Copa del Rey, Supercopa, UEFA Super Cup) → the full fixture list
//     https://www.transfermarkt.com/{slug}/gesamtspielplan/pokalwettbewerb/{id}/saison_id/{year}
//     There is no table to read; the clubs have to come off the fixtures.
//
// The fixture page must NOT be scraped by querying the whole document: it also
// carries sidebars, nav and related-club widgets whose `/verein/` links are not
// participants at all (that is how the 2026 Super Cup — a single two-club tie —
// ended up with seven clubs). Both branches therefore stay inside the page's
// content tables, and neither falls back to a document-wide query: an empty
// result makes background.js throw, which is what we want if the markup moves.
//
// Output format (identical for both shapes):
// {
//   "id": "CL",
//   "seasonId": "2025",
//   "clubs": [
//     { "id": "418", "name": "Real Madrid" },
//     ...
//   ]
// }

(function () {
  const url = window.location.href;

  // Extract competition ID from URL (e.g., /pokalwettbewerb/CL/)
  const compIdMatch = url.match(/\/pokalwettbewerb\/([A-Z0-9]+)/i);
  const competitionId = compIdMatch ? compIdMatch[1] : '';

  // Extract season ID from URL
  const seasonMatch = url.match(/saison_id\/(\d{4})/);
  const seasonId = seasonMatch ? seasonMatch[1] : '';

  const isLeaguePhaseTable = /\/tabelle\/pokalwettbewerb\//i.test(url);

  const clubs = [];
  const seenIds = new Set();

  const addClub = link => {
    const href = link.getAttribute('href') || '';
    const idMatch = href.match(/\/verein\/(\d+)/);
    if (!idMatch) return;

    const clubId = idMatch[1];
    if (seenIds.has(clubId)) return;

    const name = link.textContent.trim();

    // Skip empty or very short names (likely icons/navigation)
    if (!name || name.length < 2) return;

    seenIds.add(clubId);
    clubs.push({ id: clubId, name });
  };

  if (isLeaguePhaseTable) {
    // One participant per standings row. Taking the row's *first* named club
    // link keeps the crest link (whose text is empty) and any trailing links
    // out, and means a row can never contribute two clubs.
    document.querySelectorAll('.responsive-table table tbody tr').forEach(row => {
      const before = clubs.length;
      for (const link of row.querySelectorAll('a[href*="/verein/"]')) {
        addClub(link);
        if (clubs.length > before) break;
      }
    });
  } else {
    // Fixture tables only — never the whole document.
    document.querySelectorAll('.responsive-table a[href*="/verein/"]').forEach(addClub);
  }

  // Sort clubs alphabetically by name
  clubs.sort((a, b) => a.name.localeCompare(b.name));

  return {
    id: competitionId,
    seasonId,
    clubs
  };
})();
