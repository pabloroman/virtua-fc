// background.js — Service worker that handles long-running scraping tasks

importScripts('season-config.js', 'github.js');

/**
 * Build the kader (squad) URL for a club
 */
function buildKaderUrl(clubId, seasonId) {
  return `https://www.transfermarkt.com/club/kader/verein/${clubId}/saison_id/${seasonId}/plus/1`;
}

/**
 * Sleep helper
 */
function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Extract season ID from URL or default to current year
 */
function getSeasonId(url) {
  const match = url.match(/saison_id\/(\d{4})/);
  if (match) return match[1];
  const now = new Date();
  const year = now.getMonth() >= 6 ? now.getFullYear() : now.getFullYear() - 1;
  return String(year);
}

/**
 * Navigate to a URL and wait for it to load
 */
async function navigateAndWait(tabId, url) {
  console.log('[TM Scraper] Navigating to:', url);

  return new Promise((resolve, reject) => {
    const timeout = setTimeout(() => {
      chrome.tabs.onUpdated.removeListener(listener);
      console.log('[TM Scraper] Navigation timeout for:', url);
      reject(new Error('Navigation timeout'));
    }, 30000);

    const listener = (updatedTabId, changeInfo, tab) => {
      if (updatedTabId === tabId && changeInfo.status === 'complete') {
        chrome.tabs.onUpdated.removeListener(listener);
        clearTimeout(timeout);
        console.log('[TM Scraper] Page loaded:', url);
        // Extra delay for page to fully render
        setTimeout(() => resolve(), 1500);
      }
    };

    chrome.tabs.onUpdated.addListener(listener);
    chrome.tabs.update(tabId, { url });
  });
}

/**
 * Scrape players from the current page
 */
async function scrapePlayersFromTab(tabId) {
  console.log('[TM Scraper] Scraping players from tab:', tabId);
  try {
    const results = await chrome.scripting.executeScript({
      target: { tabId },
      files: ['content-club.js']
    });
    const players = results?.[0]?.result?.players || [];
    console.log('[TM Scraper] Found', players.length, 'players');
    return players;
  } catch (err) {
    console.error('[TM Scraper] Error scraping players:', err);
    return [];
  }
}

/**
 * Scrape clubs from the stadiums page
 */
async function scrapeClubsFromTab(tabId) {
  console.log('[TM Scraper] Scraping clubs from tab:', tabId);
  try {
    const results = await chrome.scripting.executeScript({
      target: { tabId },
      files: ['content-competition.js']
    });
    const result = results?.[0]?.result || null;
    console.log('[TM Scraper] Found', result?.clubs?.length || 0, 'clubs');
    return result;
  } catch (err) {
    console.error('[TM Scraper] Error scraping clubs:', err);
    return null;
  }
}

/**
 * Scrape fixtures from the fixtures page
 */
async function scrapeFixturesFromTab(tabId) {
  const results = await chrome.scripting.executeScript({
    target: { tabId },
    files: ['content-fixtures.js']
  });
  return results?.[0]?.result || null;
}

/**
 * Scrape cup/tournament teams from the fixtures page
 */
async function scrapeCupTeamsFromTab(tabId) {
  const results = await chrome.scripting.executeScript({
    target: { tabId },
    files: ['content-cup-teams.js']
  });
  return results?.[0]?.result || null;
}

/**
 * Main scraping function for competition + players
 */
async function scrapeCompetition(tabId, originalUrl) {
  console.log('[TM Scraper] Starting competition scrape for:', originalUrl);

  // Step 1: Scrape clubs from stadiums page
  await updateProgress({ status: 'working', message: 'Getting clubs...', current: 0, total: 0, pageType: 'competition-stadiums' });

  const clubsResult = await scrapeClubsFromTab(tabId);
  if (!clubsResult || !clubsResult.clubs || clubsResult.clubs.length === 0) {
    console.error('[TM Scraper] No clubs found');
    throw new Error('No club data found on this page.');
  }

  // Make a deep copy of clubs to avoid reference issues
  const clubs = JSON.parse(JSON.stringify(clubsResult.clubs));
  const competitionId = clubsResult.id;
  const seasonId = getSeasonId(originalUrl);
  const totalClubs = clubs.length;

  console.log('[TM Scraper] Found', totalClubs, 'clubs, season:', seasonId);
  await updateProgress({ status: 'working', message: `Found ${totalClubs} clubs`, current: 0, total: totalClubs, pageType: 'competition-stadiums' });

  // Step 2: Visit each club and scrape players
  for (let i = 0; i < clubs.length; i++) {
    const club = clubs[i];
    const clubName = club.name || `Club ${club.transfermarktId}`;

    console.log(`[TM Scraper] Processing club ${i + 1}/${totalClubs}: ${clubName}`);

    await updateProgress({
      status: 'working',
      message: `${clubName}`,
      current: i + 1,
      total: totalClubs,
      pageType: 'competition-stadiums'
    });

    try {
      const kaderUrl = buildKaderUrl(club.transfermarktId, seasonId);
      await navigateAndWait(tabId, kaderUrl);
      const players = await scrapePlayersFromTab(tabId);
      club.players = players;
      console.log(`[TM Scraper] Club ${clubName}: ${players.length} players`);
    } catch (err) {
      console.error(`[TM Scraper] Error processing club ${clubName}:`, err);
      club.players = [];
      club.error = err.message;
    }

    // Delay between requests to be respectful
    if (i < clubs.length - 1) {
      await sleep(800);
    }
  }

  // Step 3: Navigate back to original page
  console.log('[TM Scraper] All clubs processed, navigating back to:', originalUrl);
  chrome.tabs.update(tabId, { url: originalUrl });

  // Build final result
  const result = {
    id: competitionId,
    clubs,
    updatedAt: new Date().toISOString()
  };

  const totalPlayers = clubs.reduce((sum, c) => sum + (c.players?.length || 0), 0);
  console.log('[TM Scraper] Scraping complete:', totalClubs, 'clubs,', totalPlayers, 'players');

  await updateProgress({
    status: 'ready',
    message: `Done — ${competitionId}`,
    current: totalClubs,
    total: totalClubs,
    playerCount: totalPlayers,
    pageType: 'competition-stadiums',
    result
  });

  return result;
}

/**
 * Scrape fixtures (simple, no navigation needed)
 */
async function scrapeFixtures(tabId, url) {
  await updateProgress({ status: 'working', message: 'Getting fixtures...', current: 0, total: 0, pageType: 'fixtures' });

  const fixturesResult = await scrapeFixturesFromTab(tabId);
  if (!fixturesResult || !fixturesResult.matchdays || fixturesResult.matchdays.length === 0) {
    throw new Error('No fixtures data found on this page.');
  }

  const totalMatchdays = fixturesResult.matchdays.length;
  const totalMatches = fixturesResult.matchdays.reduce((sum, md) => sum + md.matches.length, 0);

  await updateProgress({
    status: 'ready',
    message: `Done — ${fixturesResult.id}`,
    current: totalMatchdays,
    total: totalMatchdays,
    pageType: 'fixtures',
    result: fixturesResult
  });

  return fixturesResult;
}

/**
 * Scrape cup teams (simple, no navigation needed)
 */
async function scrapeCupTeams(tabId, url) {
  await updateProgress({ status: 'working', message: 'Getting teams...', current: 0, total: 0, pageType: 'cup-teams' });

  const teamsResult = await scrapeCupTeamsFromTab(tabId);
  if (!teamsResult || !teamsResult.clubs || teamsResult.clubs.length === 0) {
    throw new Error('No teams data found on this page.');
  }

  const totalTeams = teamsResult.clubs.length;

  await updateProgress({
    status: 'ready',
    message: `Done — ${teamsResult.id}`,
    current: totalTeams,
    total: totalTeams,
    pageType: 'cup-teams',
    result: teamsResult
  });

  return teamsResult;
}

// ---------------------------------------------------------------------------
// Batch player positions scraper
// ---------------------------------------------------------------------------

let batchAborted = false;

/**
 * Scrape positions from a single player profile page
 */
async function scrapePlayerPositionsFromTab(tabId) {
  try {
    const results = await chrome.scripting.executeScript({
      target: { tabId },
      files: ['content-player-positions.js']
    });
    return results?.[0]?.result || null;
  } catch (err) {
    console.error('[TM Scraper] Error scraping player positions:', err);
    return null;
  }
}

/**
 * Main batch scraper for player positions.
 * Persists results to chrome.storage after every player so it can resume.
 */
async function scrapeBatchPlayerPositions(tabId, playerIds) {
  batchAborted = false;

  // Load any previously saved progress
  const stored = await chrome.storage.local.get('batchPositions');
  const completedMap = stored.batchPositions || {}; // { "581678": ["CM","LM"], ... }

  const total = playerIds.length;
  const remaining = playerIds.filter(id => !(id in completedMap));
  const alreadyDone = total - remaining.length;

  console.log(`[TM Scraper] Batch positions: ${total} total, ${alreadyDone} already done, ${remaining.length} remaining`);

  await updateProgress({
    status: 'working',
    message: `Resuming — ${alreadyDone} already done`,
    current: alreadyDone,
    total,
    pageType: 'batch-positions'
  });

  for (let i = 0; i < remaining.length; i++) {
    if (batchAborted) {
      console.log('[TM Scraper] Batch aborted by user');
      const doneCount = Object.keys(completedMap).length;
      await updateProgress({
        status: 'paused',
        message: `Paused — ${doneCount}/${total}`,
        current: doneCount,
        total,
        pageType: 'batch-positions'
      });
      return;
    }

    const playerId = remaining[i];
    const doneCount = alreadyDone + i;

    await updateProgress({
      status: 'working',
      message: `Player ${playerId}`,
      current: doneCount + 1,
      total,
      pageType: 'batch-positions'
    });

    try {
      const profileUrl = `https://www.transfermarkt.com/player/profil/spieler/${playerId}`;
      await navigateAndWait(tabId, profileUrl);
      const result = await scrapePlayerPositionsFromTab(tabId);

      completedMap[playerId] = result?.positions || [];

      console.log(`[TM Scraper] ${doneCount + 1}/${total} — Player ${playerId}: ${(result?.positions || []).join(', ') || 'no positions found'}`);
    } catch (err) {
      console.error(`[TM Scraper] Error on player ${playerId}:`, err);
      completedMap[playerId] = [];
    }

    // Persist after every player
    await chrome.storage.local.set({ batchPositions: completedMap });

    // Throttle
    if (i < remaining.length - 1) {
      await sleep(1200);
    }
  }

  // Done — build final result (omit players with no positions)
  const resultArray = playerIds
    .filter(id => (completedMap[id] || []).length > 0)
    .map(id => ({
      id,
      positions: completedMap[id]
    }));

  const withPositions = resultArray.length;

  console.log(`[TM Scraper] Batch complete: ${total} players, ${withPositions} with positions`);

  await updateProgress({
    status: 'ready',
    message: `Done — ${withPositions}/${total} with positions`,
    current: total,
    total,
    pageType: 'batch-positions',
    result: resultArray
  });

  return resultArray;
}

// ---------------------------------------------------------------------------
// GitHub push + whole-season refresh
// ---------------------------------------------------------------------------

let refreshAborted = false;

/**
 * Read the saved GitHub settings (token, repo, target season, base branch).
 */
async function getSettings() {
  const s = await chrome.storage.local.get(['ghToken', 'ghRepo', 'ghSeason', 'ghBaseBranch']);
  return {
    token: s.ghToken || '',
    repo: s.ghRepo || SeasonConfig.REPO,
    season: s.ghSeason || '',
    base: s.ghBaseBranch || SeasonConfig.BASE_BRANCH,
  };
}

/**
 * Commit a set of {path, content} files to the season-data branch as one commit
 * and ensure a PR is open. Returns the PR url.
 */
async function pushFilesToGitHub(files, season) {
  const { token, repo, base } = await getSettings();
  if (!token) throw new Error('No GitHub token set — open Settings.');
  if (files.length === 0) throw new Error('Nothing to push.');

  const gh = new GitHubClient(token, repo);
  const branch = SeasonConfig.branchFor(season);
  const message = `Season ${season} data refresh (${files.length} file${files.length === 1 ? '' : 's'})`;

  await gh.commitFiles(branch, base, files, message);

  return gh.ensurePullRequest(
    branch,
    base,
    `Season data refresh ${season}`,
    'Automated squad refresh from the Transfermarkt scraper. ' +
      'CI normalizes the files, runs `app:validate-season`, and posts a transfer diff.',
  );
}

/**
 * Scrape one league's clubs + squads: load the stadiums page for the club list,
 * then visit each club's kader page for players. Reuses the same primitives as
 * the single-page competition scrape. `onClub(index, total, name)` reports
 * per-club progress.
 */
async function scrapeLeagueSquads(tabId, stadiumsUrl, onClub) {
  await navigateAndWait(tabId, stadiumsUrl);

  const clubsResult = await scrapeClubsFromTab(tabId);
  if (!clubsResult || !clubsResult.clubs || clubsResult.clubs.length === 0) {
    throw new Error(`No clubs found at ${stadiumsUrl}`);
  }

  const clubs = JSON.parse(JSON.stringify(clubsResult.clubs));
  const seasonId = getSeasonId(stadiumsUrl);

  for (let i = 0; i < clubs.length; i++) {
    if (refreshAborted) throw new Error('aborted');

    const club = clubs[i];
    if (onClub) onClub(i + 1, clubs.length, club.name || club.transfermarktId);

    try {
      await navigateAndWait(tabId, buildKaderUrl(club.transfermarktId, seasonId));
      club.players = await scrapePlayersFromTab(tabId);
    } catch (err) {
      club.players = [];
      club.error = err.message;
    }

    if (i < clubs.length - 1) await sleep(800);
  }

  return { id: clubsResult.id, clubs };
}

/**
 * Drive every batch-enabled league for the target season, scrape its full
 * squad, and push them all to the season-data branch in one commit + PR.
 */
async function startSeasonRefresh(tabId) {
  refreshAborted = false;

  const { token, season } = await getSettings();
  if (!token) throw new Error('No GitHub token set — open Settings.');
  if (!season) throw new Error('Set a target season in Settings first.');

  const leagues = SeasonConfig.COMPETITIONS.filter(c => c.batch);
  const files = [];

  for (let i = 0; i < leagues.length; i++) {
    if (refreshAborted) {
      await updateProgress({ status: 'paused', message: `Paused — ${i}/${leagues.length} leagues`, current: i, total: leagues.length, pageType: 'season-refresh' });
      return;
    }

    const comp = leagues[i];
    await updateProgress({ status: 'working', message: `${comp.code}…`, current: i, total: leagues.length, pageType: 'season-refresh' });

    const stadiumsUrl = `https://www.transfermarkt.com/-/stadien/wettbewerb/${comp.tmId}/saison_id/${season}`;
    const result = await scrapeLeagueSquads(tabId, stadiumsUrl, (ci, ct, cn) => {
      updateProgress({ status: 'working', message: `${comp.code} — ${cn} (${ci}/${ct})`, current: i, total: leagues.length, pageType: 'season-refresh' });
    });

    const file = SeasonConfig.repoFileForResult(result, 'competition-stadiums', { season });
    if (file) files.push(file);
  }

  await updateProgress({ status: 'working', message: `Pushing ${files.length} files to GitHub…`, current: leagues.length, total: leagues.length, pageType: 'season-refresh' });

  const prUrl = await pushFilesToGitHub(files, season);

  await updateProgress({
    status: 'ready',
    message: `Done — ${files.length} leagues pushed`,
    current: leagues.length,
    total: leagues.length,
    pageType: 'season-refresh',
    result: { prUrl, files: files.length },
  });
}

/**
 * Update progress in storage so popup can read it
 */
async function updateProgress(data) {
  console.log('[TM Scraper] Progress:', data.status, '-', data.message);
  await chrome.storage.local.set({ scrapeProgress: data });
}

/**
 * Clear progress
 */
async function clearProgress() {
  await chrome.storage.local.remove('scrapeProgress');
}

// Listen for messages from popup
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.action === 'startScrape') {
    const { tabId, url, pageType, redirectUrl } = message;

    // Handle club-redirect: navigate to kader page first, then scrape
    if (pageType === 'club-redirect') {
      (async () => {
        try {
          await updateProgress({ status: 'working', message: 'Redirecting to squad page...', current: 0, total: 0, pageType: 'club' });

          // Navigate to kader URL
          await navigateAndWait(tabId, redirectUrl);

          // Now scrape the club data
          const results = await chrome.scripting.executeScript({
            target: { tabId },
            files: ['content-club.js']
          });

          const result = results?.[0]?.result;
          if (!result) {
            throw new Error('No data returned from content script');
          }

          const playerCount = result.players?.length || 0;
          await updateProgress({
            status: 'ready',
            message: `Done — ${result.name || result.transfermarktId}`,
            current: playerCount,
            total: playerCount,
            playerCount: playerCount,
            pageType: 'club',
            result
          });
        } catch (err) {
          console.error('Club redirect scraping failed:', err);
          updateProgress({ status: 'error', message: err.message });
        }
      })();

      sendResponse({ started: true });
      return true;
    }

    if (pageType === 'competition-stadiums') {
      // Run the scraping in background
      scrapeCompetition(tabId, url)
        .then(result => {
          console.log('Scraping complete:', result);
        })
        .catch(err => {
          console.error('Scraping failed:', err);
          updateProgress({ status: 'error', message: err.message });
        });

      sendResponse({ started: true });

    } else if (pageType === 'fixtures') {
      // Scrape fixtures (no navigation needed)
      scrapeFixtures(tabId, url)
        .then(result => {
          console.log('Fixtures scraping complete:', result);
        })
        .catch(err => {
          console.error('Fixtures scraping failed:', err);
          updateProgress({ status: 'error', message: err.message });
        });

      sendResponse({ started: true });

    } else if (pageType === 'cup-teams') {
      // Scrape cup/tournament teams (no navigation needed)
      scrapeCupTeams(tabId, url)
        .then(result => {
          console.log('Cup teams scraping complete:', result);
        })
        .catch(err => {
          console.error('Cup teams scraping failed:', err);
          updateProgress({ status: 'error', message: err.message });
        });

      sendResponse({ started: true });

    } else if (pageType === 'club') {
      // For single club, scrape the full result (now includes team info)
      chrome.scripting.executeScript({
        target: { tabId },
        files: ['content-club.js']
      })
        .then(results => {
          const result = results?.[0]?.result;
          if (!result) {
            throw new Error('No data returned from content script');
          }
          const playerCount = result.players?.length || 0;
          updateProgress({
            status: 'ready',
            message: `Done — ${result.name || result.transfermarktId}`,
            current: playerCount,
            total: playerCount,
            playerCount: playerCount,
            pageType: 'club',
            result
          });
        })
        .catch(err => {
          updateProgress({ status: 'error', message: err.message });
        });

      sendResponse({ started: true });
    } else {
      sendResponse({ error: 'Unsupported page type' });
    }

    return true; // Keep channel open for async response
  }

  if (message.action === 'startBatchPositions') {
    const { tabId, playerIds } = message;

    scrapeBatchPlayerPositions(tabId, playerIds)
      .then(() => console.log('[TM Scraper] Batch positions finished'))
      .catch(err => {
        console.error('[TM Scraper] Batch positions failed:', err);
        updateProgress({ status: 'error', message: err.message, pageType: 'batch-positions' });
      });

    sendResponse({ started: true });
    return true;
  }

  if (message.action === 'stopBatch') {
    batchAborted = true;
    sendResponse({ stopped: true });
    return true;
  }

  if (message.action === 'clearBatchData') {
    chrome.storage.local.remove('batchPositions', () => {
      sendResponse({ cleared: true });
    });
    return true;
  }

  if (message.action === 'getBatchStats') {
    chrome.storage.local.get('batchPositions', (data) => {
      const map = data.batchPositions || {};
      sendResponse({ completed: Object.keys(map).length });
    });
    return true;
  }

  if (message.action === 'pushScrape') {
    (async () => {
      try {
        const { season } = await getSettings();
        if (!season) throw new Error('Set a target season in Settings first.');

        const file = SeasonConfig.repoFileForResult(message.result, message.pageType, {
          season,
          pool: message.pool,
        });
        if (!file) {
          throw new Error('This page is not a known competition — cannot map it to a repo file.');
        }

        const prUrl = await pushFilesToGitHub([file], season);
        sendResponse({ ok: true, prUrl, path: file.path });
      } catch (err) {
        sendResponse({ ok: false, error: err.message });
      }
    })();
    return true;
  }

  if (message.action === 'startSeasonRefresh') {
    startSeasonRefresh(message.tabId)
      .catch(err => {
        if (err.message === 'aborted') return;
        console.error('[TM Scraper] Season refresh failed:', err);
        updateProgress({ status: 'error', message: err.message, pageType: 'season-refresh' });
      });
    sendResponse({ started: true });
    return true;
  }

  if (message.action === 'stopRefresh') {
    refreshAborted = true;
    sendResponse({ stopped: true });
    return true;
  }

  if (message.action === 'getProgress') {
    chrome.storage.local.get('scrapeProgress', (data) => {
      sendResponse(data.scrapeProgress || null);
    });
    return true;
  }

  if (message.action === 'clearProgress') {
    clearProgress().then(() => sendResponse({ cleared: true }));
    return true;
  }
});
