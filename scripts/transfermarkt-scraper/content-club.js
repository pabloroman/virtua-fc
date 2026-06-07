// content-club.js — Extracts squad/player data from a Transfermarkt club squad page.
//
// Target URL pattern: https://www.transfermarkt.com/{club-slug}/kader/verein/{id}/saison_id/{year}/plus/1
//
// Output format:
// {
//   "transfermarktId": "131",
//   "name": "FC Barcelona",
//   "image": "https://tmssl.akamaized.net/images/wappen/big/131.png",
//   "stadiumName": "Estadi Olímpic Lluís Companys",
//   "stadiumSeats": "55926",
//   "players": [ { "id": "74857", "name": "Marc-André ter Stegen", ... }, ... ]
// }

(function () {
  const url = window.location.href;

  // ---------------------------------------------------------------------------
  // 1. Extract club ID from the URL
  //    e.g. /fc-barcelona/kader/verein/131/saison_id/2025/plus/1
  // ---------------------------------------------------------------------------
  const clubIdMatch = url.match(/\/verein\/(\d+)/);
  const clubId = clubIdMatch ? clubIdMatch[1] : '';

  // ---------------------------------------------------------------------------
  // 2. Helper functions
  // ---------------------------------------------------------------------------
  function cleanText(text) {
    return (text || '').replace(/\s+/g, ' ').trim();
  }

  // ---------------------------------------------------------------------------
  // 3. Extract club/team information from the page header
  // ---------------------------------------------------------------------------
  const clubInfo = {
    transfermarktId: clubId,
    name: null,
    image: `https://tmssl.akamaized.net/images/wappen/big/${clubId}.png`,
    stadiumName: null,
    stadiumSeats: null
  };

  // Club name - from the main header
  const headerName = document.querySelector('h1.data-header__headline-wrapper');
  if (headerName) {
    // Remove any child elements text (like "Squad") and get just the club name
    const nameText = headerName.childNodes[0]?.textContent || headerName.textContent;
    clubInfo.name = cleanText(nameText);
  }

  // Alternative: get name from the tm-header component or profile header
  if (!clubInfo.name) {
    const profileHeader = document.querySelector('.data-header__profile-container img[alt]');
    if (profileHeader) {
      clubInfo.name = cleanText(profileHeader.alt);
    }
  }

  // Stadium info - from the data-header info box
  const infoItems = document.querySelectorAll('.data-header__items .data-header__content');
  infoItems.forEach(item => {
    const label = item.querySelector('.data-header__label');
    const value = item.querySelector('.data-header__content--value, a, span:not(.data-header__label)');

    if (label && value) {
      const labelText = cleanText(label.textContent).toLowerCase();
      const valueText = cleanText(value.textContent);

      if (labelText.includes('stadium')) {
        clubInfo.stadiumName = valueText;
      }
    }
  });

  // Alternative stadium extraction - from quick facts or info table
  const quickFacts = document.querySelectorAll('.data-header__details li, .data-header__info-box span');
  quickFacts.forEach(item => {
    const text = cleanText(item.textContent);
    // Look for stadium link
    const stadiumLink = item.querySelector('a[href*="/stadion/"]');
    if (stadiumLink) {
      clubInfo.stadiumName = cleanText(stadiumLink.textContent);
    }
  });

  // Try to get stadium from the info section with stadium icon
  const stadiumSpan = document.querySelector('a[href*="/stadion/"]');
  if (stadiumSpan && !clubInfo.stadiumName) {
    clubInfo.stadiumName = cleanText(stadiumSpan.textContent);
  }

  // Stadium capacity - often shown in parentheses after stadium name or in separate element
  const allText = document.body.innerText;
  if (clubInfo.stadiumName) {
    // Look for pattern like "Stadium Name (50,000)" or "50.000 Seats"
    const capacityMatch = allText.match(new RegExp(clubInfo.stadiumName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s*[\\(\\[]?([\\d.,]+)\\s*(?:seats|Seats|Plätze)?[\\)\\]]?', 'i'));
    if (capacityMatch) {
      clubInfo.stadiumSeats = capacityMatch[1].replace(/[.,]/g, '');
    }
  }

  // Alternative: look for seats in data header
  const seatsElement = document.querySelector('[class*="seats"], [class*="capacity"]');
  if (seatsElement && !clubInfo.stadiumSeats) {
    const seatsMatch = cleanText(seatsElement.textContent).match(/([\\d.,]+)/);
    if (seatsMatch) {
      clubInfo.stadiumSeats = seatsMatch[1].replace(/[.,]/g, '');
    }
  }

  function formatDate(dateStr) {
    // Convert "04/05/2001" to "May 4, 2001"
    if (!dateStr) return null;
    const match = dateStr.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (!match) return dateStr;

    const [, day, month, year] = match;
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const monthName = months[parseInt(month, 10) - 1];
    return `${monthName} ${parseInt(day, 10)}, ${year}`;
  }

  function parseDobAndAge(text) {
    // Parse "04/05/2001 (24)" into { dateOfBirth: "May 4, 2001", age: "24" }
    const match = cleanText(text).match(/^(\d{2}\/\d{2}\/\d{4})\s*\((\d+)\)$/);
    if (match) {
      return {
        dateOfBirth: formatDate(match[1]),
      };
    }
    return null;
  }

  // ---------------------------------------------------------------------------
  // 3b. Detect whether this is a national team page (vs. a club page).
  //     National team pages expose Confederation / FIFA world ranking items
  //     in the data-header and lack a stadium link.
  // ---------------------------------------------------------------------------
  const headerLabels = Array.from(document.querySelectorAll('.data-header__label'))
    .map(el => cleanText(el.textContent).toLowerCase());
  const hasNationalTeamLabel = headerLabels.some(l => /confederation|world ranking|fifa rank/.test(l));
  const hasStadiumLink = !!document.querySelector('a[href*="/stadion/"]');
  const isNationalTeam = hasNationalTeamLabel || !hasStadiumLink;

  // National team outputs use VirtuaFC-hosted crests instead of Transfermarkt's CDN.
  const crestBaseUrl = isNationalTeam
    ? 'https://assets.virtuafc.com/crests'
    : 'https://tmssl.akamaized.net/images/wappen/big';
  clubInfo.image = `${crestBaseUrl}/${clubId}.png`;

  // ---------------------------------------------------------------------------
  // 4. Extract players from the squad table
  // ---------------------------------------------------------------------------
  const players = [];
  const seenIds = new Set();

  // The detailed squad table has class "items"
  const table = document.querySelector('table.items');

  if (!table) {
    return { ...clubInfo, players: [], error: 'No squad table found' };
  }

  // Get all data rows (skip position header rows with class bg_blau_20)
  const rows = table.querySelectorAll('tbody > tr:not(.bg_blau_20)');

  rows.forEach(tr => {
    // Skip header rows
    if (tr.querySelector('th')) return;

    // Get player link from hauptlink cell to extract ID
    const playerLink = tr.querySelector('td.hauptlink a[href*="/spieler/"]');
    if (!playerLink) return;

    const href = playerLink.getAttribute('href') || '';
    const playerIdMatch = href.match(/\/spieler\/(\d+)/);
    if (!playerIdMatch) return;

    const playerId = playerIdMatch[1];
    if (seenIds.has(playerId)) return;
    seenIds.add(playerId);

    const player = { id: playerId };
    const cells = tr.querySelectorAll('td');

    // ---------------------------------------------------------------------------
    // Player name (from hauptlink cell)
    // ---------------------------------------------------------------------------
    const nameCell = tr.querySelector('td.hauptlink');
    if (nameCell) {
      player.name = cleanText(nameCell.textContent);
    }

    // ---------------------------------------------------------------------------
    // Position (from inline-table in posrela cell)
    // ---------------------------------------------------------------------------
    const positionCell = tr.querySelector('.inline-table tr:last-child td');
    if (positionCell) {
      player.position = cleanText(positionCell.textContent);
    }

    // ---------------------------------------------------------------------------
    // Injury status — Transfermarkt adds a `verletzt-table` icon next to the
    // player name when they are currently injured.
    // ---------------------------------------------------------------------------
    player.injured = !!tr.querySelector('.verletzt-table');

    // ---------------------------------------------------------------------------
    // Loan status — a loaned-in player has a `leihe` icon (img.wechsel-symbol)
    // in the posrela cell, alongside the lending club's crest. New signings and
    // departures reuse the same wrapper with zugang_/abgang_ icons, so we gate
    // strictly on the loan icon filename (locale-independent) and parse the
    // English title for the lending club and end date (consistent with the
    // English assumptions used elsewhere, e.g. the foot regex).
    // ---------------------------------------------------------------------------
    if (!isNationalTeam) {
      const posCell = tr.querySelector('td.posrela');
      const loanIcon = posCell && posCell.querySelector('img.wechsel-symbol[src*="leihe"]');
      if (loanIcon) {
        const loan = {};

        // Lending club crest + name come from the wappen anchor's img.
        const crestImg = posCell.querySelector('span.wechsel-kader-wappen a[href*="/verein/"] img');
        const crestAnchor = crestImg ? crestImg.closest('a[href*="/verein/"]') : null;
        const from = {};
        if (crestImg) {
          const fromName = cleanText(crestImg.getAttribute('title') || crestImg.getAttribute('alt'));
          if (fromName) from.name = fromName;
        }
        if (crestAnchor) {
          const idMatch = (crestAnchor.getAttribute('href') || '').match(/\/verein\/(\d+)/);
          if (idMatch) {
            from.id = idMatch[1];
            from.image = `${crestBaseUrl}/${idMatch[1]}.png`;
          }
        }
        if (from.id || from.name || from.image) loan.from = from;

        // End date from the title text ("... until DD/MM/YYYY").
        const titleEl = posCell.querySelector('a[href*="/verein/"][title*="until"]') || crestAnchor;
        const title = titleEl ? cleanText(titleEl.getAttribute('title')) : '';
        const untilMatch = title.match(/until\s+(\d{2}\/\d{2}\/\d{4})/i);
        if (untilMatch) loan.until = formatDate(untilMatch[1]);

        if (loan.from || loan.until) player.loan = loan;
      }
    }

    // Note: querySelectorAll('td') returns descendant tds too — the three
    // tds inside the inline-table (portrait, name, position) shift the
    // effective indices for both club and national team layouts identically.

    // Player number (only present on club pages — national team rows show a
    // position-coded icon in cell 0 instead of a squad number).
    if (!isNationalTeam && cells[0]) {
      const numberText = cleanText(cells[0].textContent);
      if (/^\d+$/.test(numberText)) player.number = numberText;
    }

    // Date of birth (cell 5, format: "04/05/2001 (24)")
    if (cells[5]) {
      const dobData = parseDobAndAge(cells[5].textContent);
      if (dobData) player.dateOfBirth = dobData.dateOfBirth;
    }

    // Cell 6: nationality flags on club pages, club crest on national team pages.
    if (cells[6]) {
      if (isNationalTeam) {
        const clubAnchor = cells[6].querySelector('a[href*="/startseite/verein/"]');
        const clubImg = clubAnchor?.querySelector('img');
        if (clubAnchor && clubImg) {
          const clubName = cleanText(
            clubImg.getAttribute('title') || clubImg.getAttribute('alt') || clubAnchor.getAttribute('title')
          );
          const href = clubAnchor.getAttribute('href') || '';
          const idMatch = href.match(/\/verein\/(\d+)/);
          const club = {};
          if (clubName) club.name = clubName;
          if (idMatch) club.image = `${crestBaseUrl}/${idMatch[1]}.png`;
          if (club.name || club.image) player.club = club;
        }
      } else {
        const flags = cells[6].querySelectorAll('img.flaggenrahmen');
        if (flags.length > 0) {
          player.nationality = Array.from(flags)
            .map(img => cleanText(img.alt || img.title))
            .filter(n => n);
        }
      }
    }

    // Height (cell 7, format: "1,94m")
    if (cells[7]) {
      const heightText = cleanText(cells[7].textContent);
      if (/^\d[,\.]\d{2}\s?m$/.test(heightText)) player.height = heightText;
    }

    // Foot (cell 8: "right" | "left" | "both")
    if (cells[8]) {
      const footText = cleanText(cells[8].textContent).toLowerCase();
      if (/^(right|left|both)$/.test(footText)) player.foot = footText;
    }

    // Contract end (cell 11, format: "30/06/2031") — club pages only.
    // National team rows show debut date in this cell; we omit it from the output.
    if (!isNationalTeam && cells[11]) {
      const contractDate = formatDate(cleanText(cells[11].textContent));
      if (contractDate) player.contract = contractDate;
    }

    // Market value (cell 12, format: "€30.00m")
    if (cells[12]) {
      const mvLink = cells[12].querySelector('a[href*="/marktwertverlauf/"]');
      const mvText = cleanText(mvLink?.textContent || cells[12].textContent);
      if (/^€[\d.,]+[km]?$/i.test(mvText)) player.marketValue = mvText;
    }

    // Only add player if we have at least a name
    if (player.name) {
      players.push(player);
    }
  });

  return {
    ...clubInfo,
    players,
  };
})();
