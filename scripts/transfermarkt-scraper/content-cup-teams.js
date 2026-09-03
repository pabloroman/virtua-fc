// content-cup-teams.js — Extracts the participating clubs of a cup / continental
// competition from Transfermarkt.
//
// Two page shapes, because "who is in this competition" means different things
// for a European league phase than for a domestic knockout:
//
//   Continental (UCL/UEL/UECL/UEFASUP) → the participants page
//     https://www.transfermarkt.com/{slug}/teilnehmer/pokalwettbewerb/{id}/saison_id/{year}
//     A `table.items` with one club per row — the league-phase field exactly
//     (36 for the Swiss competitions, 2 for the Super Cup).
//
//   Domestic cups (Copa del Rey, Supercopa) → the full fixture list
//     https://www.transfermarkt.com/{slug}/gesamtspielplan/pokalwettbewerb/{id}/saison_id/{year}
//     Every club that plays a tie, which for these is what we want.
//
// Do not read a European field off the fixture list: it spans the qualifying
// rounds, so it hands back every club knocked out on the way in as well. That is
// how the 2026 refresh produced 37/41/41 clubs for UCL/UEL/UECL against a
// 36-team league phase, and seven for the Super Cup's single two-club tie.
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

  const isParticipantsPage = /\/teilnehmer\/pokalwettbewerb\//i.test(url);

  const clubs = [];
  const seenIds = new Set();

  const addClub = link => {
    const href = link.getAttribute('href') || '';
    const idMatch = href.match(/\/verein\/(\d+)/);
    if (!idMatch) return;

    const clubId = idMatch[1];
    if (seenIds.has(clubId)) return;

    // Transfermarkt ships trailing whitespace in some names ("FC Ararat-Armenia ").
    const name = link.textContent.trim();

    // Skip empty or very short names (likely icons/navigation)
    if (!name || name.length < 2) return;

    seenIds.add(clubId);
    clubs.push({ id: clubId, name });
  };

  if (isParticipantsPage) {
    // One participant per row of `table.items`. The name lives in the row's
    // `hauptlink` cell; the other club link in the row wraps only the crest
    // image, so reading the hauptlink directly keeps the row to one club and
    // never depends on link order.
    document.querySelectorAll('table.items tbody tr').forEach(row => {
      const link = row.querySelector('td.hauptlink a[href*="/verein/"]');
      if (link) addClub(link);
    });
  } else {
    // Get all club links from the page
    document.querySelectorAll('a[href*="/verein/"]').forEach(addClub);
  }

  // Sort clubs alphabetically by name
  clubs.sort((a, b) => a.name.localeCompare(b.name));

  return {
    id: competitionId,
    seasonId,
    clubs
  };
})();
