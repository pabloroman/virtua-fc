// Injected into the sofifa.com page context. Returns the assembled dataset.
// `mode` is "team" or "league". Progress is reported via window.postMessage
// which popup.js listens for through chrome.scripting executeScript return polling.
async function scrapeSoFIFA() {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

  function parseMoney(s) {
    if (!s) return null;
    s = s.trim().replace(/[€$£,\s]/g, "");
    if (!s || s === "-") return null;
    let mult = 1;
    const last = s.slice(-1).toUpperCase();
    if (last === "M") { mult = 1e6; s = s.slice(0, -1); }
    else if (last === "K") { mult = 1e3; s = s.slice(0, -1); }
    else if (last === "B") { mult = 1e9; s = s.slice(0, -1); }
    const n = parseFloat(s);
    return isNaN(n) ? null : Math.round(n * mult);
  }

  function detectCurrency(s) {
    const m = (s || "").match(/[€$£]/);
    return m ? m[0] : null;
  }

  // The team sidebar lists club-level facts as
  // `<li><label>Transfer budget</label> €77.5M</li>`. Pull the text that
  // follows the matching label within its <li> (works whether the value is a
  // bare text node, an <em>, or an <a>).
  function teamInfoValue(doc, labelText) {
    const target = labelText.toLowerCase();
    const label = [...doc.querySelectorAll("li label")].find(
      (l) => l.textContent.trim().toLowerCase() === target
    );
    const li = label && label.closest("li");
    if (!li) return null;
    return li.textContent.replace(label.textContent, "").trim() || null;
  }

  // Parsed transfer budget for the team page held in `doc` (null if absent).
  function parseTransferBudget(doc) {
    return parseMoney(teamInfoValue(doc, "Transfer budget"));
  }

  function parsePlayers(doc) {
    return [...doc.querySelectorAll("table tbody tr")].map((tr) => {
      const td = tr.querySelectorAll("td");
      if (td.length < 8) return null;
      const link =
        td[1].querySelector("a[data-tippy-content]") || td[1].querySelector("a");
      const full_name = (
        (link && link.getAttribute("data-tippy-content")) ||
        (link ? link.innerText : td[1].innerText)
      ).trim();
      const name = (link ? link.innerText : td[1].innerText).trim();
      const weekly = parseMoney(td[7].innerText);
      return {
        full_name,
        name,
        overall_score: parseInt(td[3].innerText.trim(), 10),
        potential: parseInt(td[4].innerText.trim(), 10),
        value: parseMoney(td[6].innerText),
        yearly_wage: weekly == null ? null : weekly * 52,
        currency:
          detectCurrency(td[6].innerText) || detectCurrency(td[7].innerText),
      };
    }).filter((p) => p && p.name && !isNaN(p.overall_score));
  }

  const path = location.pathname;
  const title = (document.querySelector("h1")?.innerText ||
    document.title.split(" - ")[0]).trim();

  // ---- TEAM PAGE ----
  if (/^\/team\//.test(path)) {
    const players = parsePlayers(document);
    return {
      ok: true,
      kind: "team",
      data: {
        team: title,
        url: location.origin + path,
        scraped_at: new Date().toISOString(),
        transfer_budget: parseTransferBudget(document),
        player_count: players.length,
        players,
      },
    };
  }

  // ---- LEAGUE PAGE ----
  if (/^\/league\//.test(path)) {
    const links = [...document.querySelectorAll("a[href]")]
      .map((a) => ({ name: a.textContent.trim(), href: a.getAttribute("href") }))
      .filter((l) => /^\/team\/\d+\//.test(l.href) && l.name);
    const teams = [
      ...new Map(links.map((l) => [l.href.split("?")[0], l])).values(),
    ].map((l) => ({ name: l.name, href: l.href.split("?")[0] }));

    const results = [];
    for (let i = 0; i < teams.length; i++) {
      const t = teams[i];
      window.__sofifaProgress = { done: i, total: teams.length, current: t.name };
      try {
        const res = await fetch(t.href, { credentials: "same-origin" });
        const html = await res.text();
        const doc = new DOMParser().parseFromString(html, "text/html");
        const players = parsePlayers(doc);
        results.push({
          team: t.name,
          url: location.origin + t.href,
          transfer_budget: parseTransferBudget(doc),
          player_count: players.length,
          players,
        });
      } catch (e) {
        results.push({ team: t.name, error: String(e && e.message || e) });
      }
      await sleep(350); // polite rate-limiting
    }
    window.__sofifaProgress = { done: teams.length, total: teams.length, current: "" };

    const total_players = results.reduce((s, r) => s + (r.player_count || 0), 0);
    return {
      ok: true,
      kind: "league",
      data: {
        league: title,
        scraped_at: new Date().toISOString(),
        team_count: results.length,
        total_players,
        teams: results,
      },
    };
  }

  return { ok: false, error: "Not a SoFIFA team or league page." };
}
