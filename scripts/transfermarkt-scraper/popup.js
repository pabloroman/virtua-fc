// popup.js — Controls the extension popup behaviour
// Communicates with background.js for long-running scraping tasks

const scrapeBtn = document.getElementById('scrapeBtn');
const downloadBtn = document.getElementById('downloadBtn');
const preview = document.getElementById('preview');
const statusDot = document.getElementById('statusDot');
const statusText = document.getElementById('statusText');
const countBadge = document.getElementById('countBadge');

let lastResult = null;
let lastPageType = null;
let pollingInterval = null;

function setStatus(state, message) {
  statusDot.className = 'status-dot ' + state;
  statusText.textContent = message;
}

function detectPageType(url) {
  if (/\/kader\/verein\/\d+/.test(url)) {
    return 'club';
  }
  // Club schedule page - needs redirect to kader
  if (/\/spielplan\/verein\/\d+/.test(url)) {
    return 'club-redirect';
  }
  if (/\/stadien\/wettbewerb\/[A-Z0-9]+/i.test(url)) {
    return 'competition-stadiums';
  }
  // Cup fixtures page (pokalwettbewerb)
  if (/\/gesamtspielplan\/pokalwettbewerb\/[A-Z0-9]+/i.test(url)) {
    return 'cup-teams';
  }
  // League fixtures page (wettbewerb)
  if (/\/gesamtspielplan\/wettbewerb\/[A-Z0-9]+/i.test(url)) {
    return 'fixtures';
  }
  if (/\/wettbewerb\/[A-Z0-9]+/i.test(url)) {
    return 'competition';
  }
  return 'unknown';
}

/**
 * Convert a spielplan URL to a kader URL
 * e.g. /olympiakos-piraus/spielplan/verein/683/saison_id/2025
 *   -> /club/kader/verein/683/saison_id/2025/plus/1
 */
function convertToKaderUrl(url) {
  const clubIdMatch = url.match(/\/verein\/(\d+)/);
  const seasonMatch = url.match(/saison_id\/(\d{4})/);

  if (!clubIdMatch) return null;

  const clubId = clubIdMatch[1];
  const seasonId = seasonMatch ? seasonMatch[1] : new Date().getFullYear();

  return `https://www.transfermarkt.com/club/kader/verein/${clubId}/saison_id/${seasonId}/plus/1`;
}

/**
 * Poll for progress updates from background script
 */
function startPolling() {
  if (pollingInterval) clearInterval(pollingInterval);

  pollingInterval = setInterval(async () => {
    const progress = await chrome.runtime.sendMessage({ action: 'getProgress' });

    if (!progress) return;

    setStatus(progress.status, progress.message);

    if (progress.total > 0) {
      countBadge.textContent = `${progress.current}/${progress.total}`;
      countBadge.style.display = 'inline-block';
    }

    if (progress.status === 'ready' && progress.result) {
      stopPolling();
      lastResult = progress.result;
      lastPageType = progress.pageType;

      // Update badge based on result type
      if (progress.result.matchdays) {
        const totalMatchdays = progress.result.matchdays.length;
        const totalMatches = progress.result.matchdays.reduce((sum, md) => sum + md.matches.length, 0);
        countBadge.textContent = `${totalMatchdays} matchdays, ${totalMatches} matches`;
      } else if (progress.pageType === 'cup-teams' && progress.result.clubs) {
        countBadge.textContent = `${progress.result.clubs.length} teams`;
      } else if (progress.result.clubs) {
        const totalPlayers = progress.playerCount || 0;
        const totalClubs = progress.result.clubs.length;
        countBadge.textContent = `${totalClubs} clubs, ${totalPlayers} players`;
      } else if (progress.result.players) {
        countBadge.textContent = `${progress.result.players.length} players`;
      }

      // Show preview
      const jsonStr = JSON.stringify(lastResult, null, 2);
      const maxPreview = 1500;
      preview.textContent = jsonStr.length > maxPreview
        ? jsonStr.slice(0, maxPreview) + '\n\n  … (truncated)'
        : jsonStr;
      preview.style.display = 'block';

      downloadBtn.disabled = false;
      scrapeBtn.disabled = false;
      showPushFor(lastResult, lastPageType);

      // Clear progress in storage
      chrome.runtime.sendMessage({ action: 'clearProgress' });
    }

    if (progress.status === 'error') {
      stopPolling();
      scrapeBtn.disabled = false;
    }
  }, 500);
}

function stopPolling() {
  if (pollingInterval) {
    clearInterval(pollingInterval);
    pollingInterval = null;
  }
}

// Check for any existing progress on popup open
async function checkExistingProgress() {
  const progress = await chrome.runtime.sendMessage({ action: 'getProgress' });

  // Batch positions and season refresh share the progress key but are driven by
  // their own checkers; ignore them here.
  if (progress && (progress.pageType === 'batch-positions' || progress.pageType === 'season-refresh')) {
    return;
  }

  if (progress && progress.status === 'working') {
    // Resume showing progress
    setStatus(progress.status, progress.message);
    if (progress.total > 0) {
      countBadge.textContent = `${progress.current}/${progress.total}`;
      countBadge.style.display = 'inline-block';
    }
    scrapeBtn.disabled = true;
    startPolling();
  } else if (progress && progress.status === 'ready' && progress.result) {
    // Show completed result
    lastResult = progress.result;
    lastPageType = progress.pageType;
    setStatus('ready', progress.message);

    if (progress.result.matchdays) {
      const totalMatchdays = progress.result.matchdays.length;
      const totalMatches = progress.result.matchdays.reduce((sum, md) => sum + md.matches.length, 0);
      countBadge.textContent = `${totalMatchdays} matchdays, ${totalMatches} matches`;
    } else if (progress.pageType === 'cup-teams' && progress.result.clubs) {
      countBadge.textContent = `${progress.result.clubs.length} teams`;
    } else if (progress.result.clubs) {
      const totalPlayers = progress.playerCount || 0;
      const totalClubs = progress.result.clubs.length;
      countBadge.textContent = `${totalClubs} clubs, ${totalPlayers} players`;
    } else if (progress.result.players) {
      countBadge.textContent = `${progress.result.players.length} players`;
    }
    countBadge.style.display = 'inline-block';

    const jsonStr = JSON.stringify(lastResult, null, 2);
    const maxPreview = 1500;
    preview.textContent = jsonStr.length > maxPreview
      ? jsonStr.slice(0, maxPreview) + '\n\n  … (truncated)'
      : jsonStr;
    preview.style.display = 'block';

    downloadBtn.disabled = false;
    showPushFor(lastResult, lastPageType);
  }
}

// Run on popup open
checkExistingProgress();

// ---- Scrape button ----
scrapeBtn.addEventListener('click', async () => {
  setStatus('working', 'Starting...');
  scrapeBtn.disabled = true;
  downloadBtn.disabled = true;
  preview.style.display = 'none';
  countBadge.style.display = 'none';
  lastResult = null;

  // Clear any previous progress
  await chrome.runtime.sendMessage({ action: 'clearProgress' });

  try {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

    if (!tab || !tab.url) {
      throw new Error('Cannot access the current tab.');
    }

    let pageType = detectPageType(tab.url);
    let targetUrl = tab.url;

    if (pageType === 'unknown') {
      throw new Error('Unsupported page type.');
    }

    if (pageType === 'competition') {
      throw new Error('Please navigate to stadiums (/stadien/) or fixtures (/gesamtspielplan/) page.');
    }

    // Handle redirect from spielplan to kader page
    if (pageType === 'club-redirect') {
      const kaderUrl = convertToKaderUrl(tab.url);
      if (!kaderUrl) {
        throw new Error('Could not determine kader URL from this page.');
      }

      setStatus('working', 'Redirecting to squad page...');

      // Send message to background to navigate and scrape
      const response = await chrome.runtime.sendMessage({
        action: 'startScrape',
        tabId: tab.id,
        url: tab.url,
        pageType: 'club-redirect',
        redirectUrl: kaderUrl
      });

      if (response.error) {
        throw new Error(response.error);
      }

      // Start polling for progress updates
      startPolling();
      return;
    }

    // Send message to background script to start scraping
    const response = await chrome.runtime.sendMessage({
      action: 'startScrape',
      tabId: tab.id,
      url: targetUrl,
      pageType
    });

    if (response.error) {
      throw new Error(response.error);
    }

    // Start polling for progress updates
    startPolling();

  } catch (err) {
    setStatus('error', err.message || 'Something went wrong');
    scrapeBtn.disabled = false;
  }
});

// ---- Download button ----
downloadBtn.addEventListener('click', () => {
  if (!lastResult) return;

  const blob = new Blob([JSON.stringify(lastResult, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);

  let safeName;
  if (lastPageType === 'fixtures') {
    safeName = `${(lastResult.id || 'competition').toLowerCase()}_fixtures`;
  } else if (lastPageType === 'cup-teams') {
    safeName = `${(lastResult.id || 'competition').toLowerCase()}_teams`;
  } else if (lastPageType === 'club' || lastResult.players) {
    safeName = lastResult.transfermarktId || lastResult.id || 'club';
  } else {
    safeName = (lastResult.id || 'competition').toLowerCase();
  }

  const a = document.createElement('a');
  a.href = url;
  a.download = `${safeName}.json`;
  a.click();
  URL.revokeObjectURL(url);
});

// =========================================================================
// BATCH PLAYER POSITIONS
// =========================================================================

const batchStartBtn = document.getElementById('batchStartBtn');
const batchStopBtn = document.getElementById('batchStopBtn');
const batchDownloadBtn = document.getElementById('batchDownloadBtn');
const batchResetBtn = document.getElementById('batchResetBtn');
const batchStatusDot = document.getElementById('batchStatusDot');
const batchStatusText = document.getElementById('batchStatusText');
const batchCountBadge = document.getElementById('batchCountBadge');
const batchProgressBar = document.getElementById('batchProgressBar');
const batchProgressFill = document.getElementById('batchProgressFill');
const csvSelect = document.getElementById('csvSelect');

// Hardcoded player IDs from the CSV (loaded at build time via fetch)
let batchPlayerIds = [];
let batchResult = null;
let batchPollingInterval = null;

function setBatchStatus(state, message) {
  batchStatusDot.className = 'status-dot ' + state;
  batchStatusText.textContent = message;
}

function setBatchProgress(current, total) {
  if (total === 0) return;
  const pct = ((current / total) * 100).toFixed(1);
  batchProgressBar.style.display = 'block';
  batchProgressFill.style.width = pct + '%';
  batchCountBadge.textContent = `${current}/${total} (${pct}%)`;
  batchCountBadge.style.display = 'inline-block';
}

function startBatchPolling() {
  if (batchPollingInterval) clearInterval(batchPollingInterval);

  batchPollingInterval = setInterval(async () => {
    const progress = await chrome.runtime.sendMessage({ action: 'getProgress' });
    if (!progress || progress.pageType !== 'batch-positions') return;

    setBatchStatus(progress.status, progress.message);

    if (progress.total > 0) {
      setBatchProgress(progress.current, progress.total);
    }

    if (progress.status === 'ready' && progress.result) {
      stopBatchPolling();
      batchResult = progress.result;
      batchStartBtn.disabled = false;
      batchStopBtn.disabled = true;
      batchDownloadBtn.disabled = false;
      chrome.runtime.sendMessage({ action: 'clearProgress' });
    }

    if (progress.status === 'paused') {
      stopBatchPolling();
      batchStartBtn.disabled = false;
      batchStopBtn.disabled = true;
    }

    if (progress.status === 'error') {
      stopBatchPolling();
      batchStartBtn.disabled = false;
      batchStopBtn.disabled = true;
    }
  }, 500);
}

function stopBatchPolling() {
  if (batchPollingInterval) {
    clearInterval(batchPollingInterval);
    batchPollingInterval = null;
  }
}

// Load player IDs from the selected CSV
async function loadPlayerIds() {
  try {
    const csvFile = csvSelect.value;
    const resp = await fetch(chrome.runtime.getURL(csvFile));
    const text = await resp.text();
    batchPlayerIds = text.trim().split('\n')
      .slice(1) // skip header
      .map(line => line.trim())
      .filter(id => /^\d+$/.test(id));

    // Check how many are already done
    const stats = await chrome.runtime.sendMessage({ action: 'getBatchStats' });
    const done = stats?.completed || 0;

    if (done > 0 && done < batchPlayerIds.length) {
      setBatchStatus('paused', `Paused — ${done}/${batchPlayerIds.length} done`);
      setBatchProgress(done, batchPlayerIds.length);
      batchDownloadBtn.disabled = done === 0;
    } else if (done >= batchPlayerIds.length) {
      setBatchStatus('ready', `Complete — ${batchPlayerIds.length} players`);
      setBatchProgress(batchPlayerIds.length, batchPlayerIds.length);
      batchDownloadBtn.disabled = false;
    } else {
      setBatchStatus('', `Idle — ${batchPlayerIds.length.toLocaleString()} players in queue`);
    }
  } catch (err) {
    setBatchStatus('error', 'Could not load player IDs');
    console.error('Failed to load player IDs:', err);
  }
}

// Reload player IDs when the CSV selection changes
csvSelect.addEventListener('change', loadPlayerIds);

// Check if batch is currently running
async function checkBatchProgress() {
  const progress = await chrome.runtime.sendMessage({ action: 'getProgress' });
  if (progress && progress.pageType === 'batch-positions' && progress.status === 'working') {
    setBatchStatus('working', progress.message);
    if (progress.total > 0) setBatchProgress(progress.current, progress.total);
    batchStartBtn.disabled = true;
    batchStopBtn.disabled = false;
    startBatchPolling();
  }
}

loadPlayerIds();
checkBatchProgress();

// ---- Batch Start / Resume ----
batchStartBtn.addEventListener('click', async () => {
  if (batchPlayerIds.length === 0) {
    setBatchStatus('error', 'No player IDs loaded');
    return;
  }

  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  if (!tab) {
    setBatchStatus('error', 'No active tab');
    return;
  }

  batchStartBtn.disabled = true;
  batchStopBtn.disabled = false;
  batchDownloadBtn.disabled = true;
  setBatchStatus('working', 'Starting...');

  await chrome.runtime.sendMessage({ action: 'clearProgress' });

  const response = await chrome.runtime.sendMessage({
    action: 'startBatchPositions',
    tabId: tab.id,
    playerIds: batchPlayerIds
  });

  if (response?.started) {
    startBatchPolling();
  }
});

// ---- Batch Stop ----
batchStopBtn.addEventListener('click', async () => {
  batchStopBtn.disabled = true;
  setBatchStatus('working', 'Stopping...');
  await chrome.runtime.sendMessage({ action: 'stopBatch' });
});

// ---- Batch Download ----
batchDownloadBtn.addEventListener('click', async () => {
  // If we have the full result in memory, use it
  if (batchResult) {
    downloadBatchResult(batchResult);
    return;
  }

  // Otherwise build from stored data
  const stored = await chrome.storage.local.get('batchPositions');
  const map = stored.batchPositions || {};

  if (Object.keys(map).length === 0) {
    setBatchStatus('error', 'No data to download');
    return;
  }

  const result = batchPlayerIds
    .filter(id => id in map && (map[id] || []).length > 0)
    .map(id => ({
      id,
      positions: map[id]
    }));

  downloadBatchResult(result);
});

function downloadBatchResult(data) {
  const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'player_positions.json';
  a.click();
  URL.revokeObjectURL(url);
}

// ---- Batch Reset ----
batchResetBtn.addEventListener('click', async () => {
  if (!confirm('This will delete all saved progress. Are you sure?')) return;

  await chrome.runtime.sendMessage({ action: 'stopBatch' });
  await chrome.runtime.sendMessage({ action: 'clearBatchData' });
  await chrome.runtime.sendMessage({ action: 'clearProgress' });

  batchResult = null;
  batchProgressBar.style.display = 'none';
  batchProgressFill.style.width = '0%';
  batchCountBadge.style.display = 'none';
  batchDownloadBtn.disabled = true;
  batchStartBtn.disabled = false;
  batchStopBtn.disabled = true;
  setBatchStatus('', `Idle — ${batchPlayerIds.length.toLocaleString()} players in queue`);
});

// =========================================================================
// GITHUB SETTINGS
// =========================================================================

const ghTokenEl = document.getElementById('ghToken');
const ghSeasonEl = document.getElementById('ghSeason');
const ghRepoEl = document.getElementById('ghRepo');
const ghBaseEl = document.getElementById('ghBaseBranch');
const saveSettingsBtn = document.getElementById('saveSettingsBtn');
const settingsStatus = document.getElementById('settingsStatus');

async function loadSettings() {
  const s = await chrome.storage.local.get(['ghToken', 'ghSeason', 'ghRepo', 'ghBaseBranch']);
  ghTokenEl.value = s.ghToken || '';
  ghSeasonEl.value = s.ghSeason || '';
  ghRepoEl.value = s.ghRepo || (window.SeasonConfig ? SeasonConfig.REPO : '');
  ghBaseEl.value = s.ghBaseBranch || (window.SeasonConfig ? SeasonConfig.BASE_BRANCH : 'main');

  // Surface the panel on first run so the token/season get set.
  if (!s.ghToken || !s.ghSeason) {
    document.getElementById('settings').open = true;
  }
}

saveSettingsBtn.addEventListener('click', async () => {
  await chrome.storage.local.set({
    ghToken: ghTokenEl.value.trim(),
    ghSeason: ghSeasonEl.value.trim(),
    ghRepo: ghRepoEl.value.trim() || (window.SeasonConfig ? SeasonConfig.REPO : ''),
    ghBaseBranch: ghBaseEl.value.trim() || 'main',
  });
  settingsStatus.textContent = 'Saved ✓';
  setTimeout(() => { settingsStatus.textContent = ''; }, 2000);
});

loadSettings();

// =========================================================================
// PUSH CURRENT SCRAPE TO GITHUB
// =========================================================================

const pushBtn = document.getElementById('pushBtn');
const poolSelect = document.getElementById('poolSelect');
const pushResult = document.getElementById('pushResult');

let pushPageType = null;

// Decide whether the current result can be mapped to a repo file, and reveal
// the pool selector for single-club (pool member) pages.
function showPushFor(result, pageType) {
  pushPageType = pageType;
  pushResult.style.display = 'none';

  if (pageType === 'club') {
    poolSelect.style.display = 'block';
    pushBtn.disabled = false;
    return;
  }

  poolSelect.style.display = 'none';
  const mappable = !!(result && window.SeasonConfig && SeasonConfig.findByTmId(result.id));
  pushBtn.disabled = !mappable;
}

pushBtn.addEventListener('click', async () => {
  if (!lastResult) return;

  pushBtn.disabled = true;
  pushResult.style.display = 'block';
  pushResult.textContent = 'Pushing to GitHub…';

  const resp = await chrome.runtime.sendMessage({
    action: 'pushScrape',
    result: lastResult,
    pageType: pushPageType,
    pool: poolSelect.value,
  });

  if (resp && resp.ok) {
    pushResult.innerHTML = `Pushed <strong>${resp.path}</strong> — <a href="${resp.prUrl}" target="_blank">open PR ↗</a>`;
  } else {
    pushResult.innerHTML = `<strong style="color:#fc8181">Push failed:</strong> ${resp ? resp.error : 'unknown error'}`;
  }

  pushBtn.disabled = false;
});

// =========================================================================
// SEASON REFRESH (all leagues → one PR)
// =========================================================================

const refreshStartBtn = document.getElementById('refreshStartBtn');
const refreshStopBtn = document.getElementById('refreshStopBtn');
const refreshStatusDot = document.getElementById('refreshStatusDot');
const refreshStatusText = document.getElementById('refreshStatusText');
const refreshCountBadge = document.getElementById('refreshCountBadge');
const refreshProgressBar = document.getElementById('refreshProgressBar');
const refreshProgressFill = document.getElementById('refreshProgressFill');
const refreshResult = document.getElementById('refreshResult');

let refreshPollingInterval = null;

function setRefreshStatus(state, message) {
  refreshStatusDot.className = 'status-dot ' + state;
  refreshStatusText.textContent = message;
}

function setRefreshProgress(current, total) {
  if (total === 0) return;
  const pct = ((current / total) * 100).toFixed(0);
  refreshProgressBar.style.display = 'block';
  refreshProgressFill.style.width = pct + '%';
  refreshCountBadge.textContent = `${current}/${total} leagues`;
  refreshCountBadge.style.display = 'inline-block';
}

function startRefreshPolling() {
  if (refreshPollingInterval) clearInterval(refreshPollingInterval);

  refreshPollingInterval = setInterval(async () => {
    const progress = await chrome.runtime.sendMessage({ action: 'getProgress' });
    if (!progress || progress.pageType !== 'season-refresh') return;

    setRefreshStatus(progress.status, progress.message);
    if (progress.total > 0) setRefreshProgress(progress.current, progress.total);

    if (progress.status === 'ready') {
      stopRefreshPolling();
      refreshStartBtn.disabled = false;
      refreshStopBtn.disabled = true;
      if (progress.result && progress.result.prUrl) {
        refreshResult.style.display = 'block';
        refreshResult.innerHTML = `Pushed ${progress.result.files} leagues — <a href="${progress.result.prUrl}" target="_blank">open PR ↗</a>`;
      }
      chrome.runtime.sendMessage({ action: 'clearProgress' });
    }

    if (progress.status === 'paused' || progress.status === 'error') {
      stopRefreshPolling();
      refreshStartBtn.disabled = false;
      refreshStopBtn.disabled = true;
    }
  }, 500);
}

function stopRefreshPolling() {
  if (refreshPollingInterval) {
    clearInterval(refreshPollingInterval);
    refreshPollingInterval = null;
  }
}

async function checkRefreshProgress() {
  const progress = await chrome.runtime.sendMessage({ action: 'getProgress' });
  if (progress && progress.pageType === 'season-refresh' && progress.status === 'working') {
    setRefreshStatus('working', progress.message);
    if (progress.total > 0) setRefreshProgress(progress.current, progress.total);
    refreshStartBtn.disabled = true;
    refreshStopBtn.disabled = false;
    startRefreshPolling();
  }
}

checkRefreshProgress();

refreshStartBtn.addEventListener('click', async () => {
  const { ghToken, ghSeason } = await chrome.storage.local.get(['ghToken', 'ghSeason']);
  if (!ghToken || !ghSeason) {
    setRefreshStatus('error', 'Set GitHub token + season in Settings first');
    document.getElementById('settings').open = true;
    return;
  }

  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  if (!tab) {
    setRefreshStatus('error', 'No active tab');
    return;
  }
  if (!/transfermarkt\.com/.test(tab.url || '')) {
    setRefreshStatus('error', 'Open a Transfermarkt tab first');
    return;
  }

  refreshResult.style.display = 'none';
  refreshStartBtn.disabled = true;
  refreshStopBtn.disabled = false;
  setRefreshStatus('working', 'Starting…');

  await chrome.runtime.sendMessage({ action: 'clearProgress' });
  const response = await chrome.runtime.sendMessage({ action: 'startSeasonRefresh', tabId: tab.id });
  if (response && response.started) {
    startRefreshPolling();
  }
});

refreshStopBtn.addEventListener('click', async () => {
  refreshStopBtn.disabled = true;
  setRefreshStatus('working', 'Stopping…');
  await chrome.runtime.sendMessage({ action: 'stopRefresh' });
});
