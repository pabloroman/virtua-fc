// content-fixtures.js — Extracts season fixtures from a Transfermarkt competition fixtures page.
//
// Target URL pattern: https://www.transfermarkt.com/{league}/gesamtspielplan/wettbewerb/{id}/saison_id/{year}
//
// Output format:
// {
//   "id": "ES2",
//   "seasonId": "2025",
//   "matchdays": [
//     {
//       "matchday": 1,
//       "date": "15/08/25",
//       "matches": [
//         { "homeTeamId": "1536", "awayTeamId": "4542" },
//         ...
//       ]
//     },
//     ...
//   ]
// }

(function () {
  const url = window.location.href;

  // Extract competition ID from URL
  const compIdMatch = url.match(/\/wettbewerb\/([A-Z0-9]+)/i);
  const competitionId = compIdMatch ? compIdMatch[1] : '';

  // Extract season ID from URL
  const seasonMatch = url.match(/saison_id\/(\d{4})/);
  const seasonId = seasonMatch ? seasonMatch[1] : '';

  // Find all tables on the page
  const allTables = document.querySelectorAll('table');
  const matchdays = [];

  allTables.forEach((table) => {
    // Look for matchday header in previous sibling element
    const prevEl = table.previousElementSibling;
    if (!prevEl) return;

    const matchdayMatch = prevEl.textContent.match(/(\d+)\.\s*Matchday/i);
    if (!matchdayMatch) return;

    const matchdayNum = parseInt(matchdayMatch[1]);
    const rows = table.querySelectorAll('tbody > tr');
    const matches = [];
    const allDates = []; // Collect all dates with their day of week

    rows.forEach((tr) => {
      const cells = tr.querySelectorAll('td');
      if (cells.length < 7) return;

      // Collect all dates - look for the day prefix (e.g., "Sun 17/08/25")
      const dateCell = tr.querySelector('td:first-child');
      if (dateCell) {
        const fullText = dateCell.textContent.trim();
        // Match pattern like "Sun 17/08/25" or "Wed 20/08/25"
        const dayMatch = fullText.match(/^(Sun|Mon|Tue|Wed|Thu|Fri|Sat)\s+(\d{2}\/\d{2}\/\d{2})/i);
        if (dayMatch) {
          allDates.push({ day: dayMatch[1], date: dayMatch[2] });
        }
      }

      // Extract home and away team links
      const homeLinks = [];
      const awayLinks = [];

      cells.forEach((cell, cellIdx) => {
        const vereinLink = cell.querySelector('a[href*="/verein/"]');
        if (vereinLink) {
          const href = vereinLink.getAttribute('href');
          const idMatch = href.match(/\/verein\/(\d+)/);
          const teamName = vereinLink.textContent.trim();

          if (teamName && idMatch) {
            // Home team is in first half of cells, away team in second half
            if (cellIdx < 4) {
              homeLinks.push({ id: idMatch[1], name: teamName });
            } else {
              awayLinks.push({ id: idMatch[1], name: teamName });
            }
          }
        }
      });

      // Get the first valid team from each side
      const homeTeam = homeLinks.find(t => t.name.length > 0);
      const awayTeam = awayLinks.find(t => t.name.length > 0);

      if (homeTeam && awayTeam) {
        matches.push({
          homeTeamId: homeTeam.id,
          awayTeamId: awayTeam.id
        });
      }
    });

    if (matches.length > 0) {
      // Pick Sunday date first, then Wednesday as fallback, then first available
      let dateForMatchday = null;

      // Look for Sunday first (main weekend matchday)
      const sundayMatch = allDates.find(d => d.day.toLowerCase() === 'sun');
      if (sundayMatch) {
        dateForMatchday = sundayMatch.date;
      } else {
        // Fallback to Wednesday (midweek matchday)
        const wednesdayMatch = allDates.find(d => d.day.toLowerCase() === 'wed');
        if (wednesdayMatch) {
          dateForMatchday = wednesdayMatch.date;
        } else if (allDates.length > 0) {
          // Last fallback: first available date
          dateForMatchday = allDates[0].date;
        }
      }

      matchdays.push({
        matchday: matchdayNum,
        date: dateForMatchday,
        matches
      });
    }
  });

  // Sort matchdays by number
  matchdays.sort((a, b) => a.matchday - b.matchday);

  return {
    id: competitionId,
    seasonId,
    matchdays
  };
})();
