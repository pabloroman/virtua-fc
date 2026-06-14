const goBtn = document.getElementById("go");
const statusEl = document.getElementById("status");
const pageTypeEl = document.getElementById("pageType");
const bar = document.querySelector(".bar");
const fill = document.getElementById("fill");

let currentTab = null;

function setStatus(msg, isErr) {
  statusEl.textContent = msg;
  statusEl.className = isErr ? "err" : "";
}

function sanitize(name) {
  return name.replace(/[\\/:*?"<>|]+/g, "_").trim() || "sofifa";
}

// Detect page type on open
(async () => {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  currentTab = tab;
  const url = tab?.url || "";
  if (/sofifa\.com\/team\//.test(url)) {
    pageTypeEl.textContent = "Team page detected — scrapes this team.";
    goBtn.disabled = false;
    goBtn.textContent = "Scrape team";
  } else if (/sofifa\.com\/league\//.test(url)) {
    pageTypeEl.textContent = "League page detected — scrapes all teams.";
    goBtn.disabled = false;
    goBtn.textContent = "Scrape league";
  } else {
    pageTypeEl.textContent = "Open a SoFIFA team or league page.";
    goBtn.disabled = true;
  }
})();

// Poll page-context progress while a league scrape runs
async function pollProgress() {
  try {
    const [res] = await chrome.scripting.executeScript({
      target: { tabId: currentTab.id },
      func: () => window.__sofifaProgress || null,
    });
    const p = res?.result;
    if (p && p.total) {
      bar.style.display = "block";
      fill.style.width = Math.round((p.done / p.total) * 100) + "%";
      setStatus(
        p.current
          ? `Fetching ${p.done + 1}/${p.total}: ${p.current}…`
          : `Finalizing ${p.done}/${p.total}…`
      );
    }
  } catch (_) {}
}

goBtn.addEventListener("click", async () => {
  goBtn.disabled = true;
  setStatus("Starting…");
  bar.style.display = "none";
  fill.style.width = "0";

  const poller = setInterval(pollProgress, 400);

  try {
    const [exec] = await chrome.scripting.executeScript({
      target: { tabId: currentTab.id },
      files: ["scraper.js"],
    });
    // scraper.js defines scrapeSoFIFA(); now call it and await the result
    const [out] = await chrome.scripting.executeScript({
      target: { tabId: currentTab.id },
      func: () => scrapeSoFIFA(),
    });
    clearInterval(poller);

    const result = out?.result;
    if (!result || !result.ok) {
      setStatus(result?.error || "Scrape failed.", true);
      goBtn.disabled = false;
      return;
    }

    const data = result.data;
    const fileBase =
      result.kind === "league" ? data.league : data.team;
    const json = JSON.stringify(data, null, 2);
    const blob = new Blob([json], { type: "application/json" });
    const url = URL.createObjectURL(blob);

    await chrome.downloads.download({
      url,
      filename: sanitize(fileBase) + ".json",
      saveAs: true,
    });

    const summary =
      result.kind === "league"
        ? `Done: ${data.team_count} teams, ${data.total_players} players.`
        : `Done: ${data.player_count} players.`;
    setStatus(summary);
    fill.style.width = "100%";
    bar.style.display = "block";
  } catch (e) {
    clearInterval(poller);
    setStatus("Error: " + (e?.message || e), true);
  } finally {
    goBtn.disabled = false;
  }
});
