// content-competition.js — Extracts club data with stadium info from a Transfermarkt competition stadiums page.
//
// Target URL pattern: https://www.transfermarkt.com/{league}/stadien/wettbewerb/{id}
//
// Output format:
// {
//   "id": "ES1",
//   "clubs": [ { "transfermarktId": "418", "name": "Real Madrid", ... }, ... ],
//   "updatedAt": "2024-08-31T21:41:24.208456"
// }

(function () {
  const url = window.location.href;

  // ---------------------------------------------------------------------------
  // 1. Extract competition ID from the URL
  //    e.g. /laliga/stadien/wettbewerb/ES1
  // ---------------------------------------------------------------------------
  const compIdMatch = url.match(/\/wettbewerb\/([A-Z0-9]+)/i);
  const competitionId = compIdMatch ? compIdMatch[1] : '';

  // ---------------------------------------------------------------------------
  // 2. Helper function
  // ---------------------------------------------------------------------------
  function cleanText(text) {
    return (text || '').replace(/\s+/g, ' ').trim();
  }

  // ---------------------------------------------------------------------------
  // 3. Extract clubs from the stadiums table
  // ---------------------------------------------------------------------------
  const clubs = [];
  const seenIds = new Set();

  const respTable = document.querySelector('.responsive-table');
  const mainTable = respTable?.querySelector('table');

  if (!mainTable) {
    return { id: competitionId, clubs: [], updatedAt: new Date().toISOString(), error: 'No stadium table found' };
  }

  const rows = mainTable.querySelectorAll('tbody > tr');

  rows.forEach(tr => {
    // Find club link to get ID
    const clubLink = tr.querySelector('a[href*="/verein/"]');
    if (!clubLink) return;

    const href = clubLink.getAttribute('href') || '';
    const idMatch = href.match(/\/verein\/(\d+)/);
    if (!idMatch) return;

    const clubId = idMatch[1];
    if (seenIds.has(clubId)) return;
    seenIds.add(clubId);

    const club = {
      transfermarktId: clubId
    };

    // ---------------------------------------------------------------------------
    // Club name - from image alt attribute
    // ---------------------------------------------------------------------------
    const clubImg = tr.querySelector('img[alt]');
    if (clubImg && clubImg.alt) {
      club.name = cleanText(clubImg.alt);
    }

    // ---------------------------------------------------------------------------
    // Club image - construct the big wappen URL
    // ---------------------------------------------------------------------------
    club.image = `https://tmssl.akamaized.net/images/wappen/big/${clubId}.png`;

    // ---------------------------------------------------------------------------
    // Stadium name - from hauptlink cell
    // ---------------------------------------------------------------------------
    const hauptlinkCell = tr.querySelector('td.hauptlink');
    if (hauptlinkCell) {
      club.stadiumName = cleanText(hauptlinkCell.textContent);
    }

    // ---------------------------------------------------------------------------
    // Stadium seats - from first rechts cell (capacity)
    // ---------------------------------------------------------------------------
    const rechtsCells = tr.querySelectorAll('td.rechts');
    if (rechtsCells.length > 0) {
      const capacityText = cleanText(rechtsCells[0].textContent).replace(/\./g, '');
      if (/^\d+$/.test(capacityText)) {
        club.stadiumSeats = capacityText;
      }
    }

    // Only add club if we have required fields
    if (club.transfermarktId && club.name) {
      clubs.push(club);
    }
  });

  return {
    id: competitionId,
    clubs,
    updatedAt: new Date().toISOString()
  };
})();
