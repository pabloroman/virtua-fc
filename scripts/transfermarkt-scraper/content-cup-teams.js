// content-cup-teams.js — Extracts participating teams from a Transfermarkt cup/tournament fixtures page.
//
// Target URL pattern: https://www.transfermarkt.com/{competition}/gesamtspielplan/pokalwettbewerb/{id}/saison_id/{year}
//
// Output format:
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

  // Find all unique clubs from the fixture tables
  const clubs = [];
  const seenIds = new Set();

  // Get all club links from the page
  const clubLinks = document.querySelectorAll('a[href*="/verein/"]');

  clubLinks.forEach(link => {
    const href = link.getAttribute('href') || '';
    const idMatch = href.match(/\/verein\/(\d+)/);
    if (!idMatch) return;

    const clubId = idMatch[1];
    if (seenIds.has(clubId)) return;

    // Get the club name from the link text
    let name = link.textContent.trim();

    // Skip empty or very short names (likely icons/navigation)
    if (!name || name.length < 2) return;

    seenIds.add(clubId);
    clubs.push({
      id: clubId,
      name: name
    });
  });

  // Sort clubs alphabetically by name
  clubs.sort((a, b) => a.name.localeCompare(b.name));

  return {
    id: competitionId,
    seasonId,
    clubs
  };
})();
