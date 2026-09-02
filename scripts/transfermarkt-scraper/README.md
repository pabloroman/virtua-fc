# Transfermarkt Scraper — Chrome Extension

A lightweight Chrome extension that extracts table data from any Transfermarkt page and exports it as a JSON file.

## Installation

1. Open Chrome and go to `chrome://extensions/`
2. Enable **Developer mode** (toggle in the top-right corner)
3. Click **Load unpacked**
4. Select the `transfermarkt-scraper` folder (the unzipped folder)
5. The extension icon will appear in your toolbar

## Usage

1. Navigate to any Transfermarkt page, e.g.  
   `https://www.transfermarkt.com/laliga/startseite/wettbewerb/ES1`
2. Click the extension icon in the toolbar
3. Click **Scrape This Page**
4. Review the preview of extracted data
5. Click **Download JSON** to save the file

## What Gets Extracted

The scraper finds all data tables on the page and extracts:

- **Text content** from every cell
- **Links** (text + href) when cells contain anchors
- **Images** (src + alt) such as club logos and player photos
- **Headers** are used as JSON keys for each row

## Output Format

```json
{
  "url": "https://www.transfermarkt.com/...",
  "title": "Page title",
  "scraped_at": "2025-01-15T12:00:00.000Z",
  "total_rows": 20,
  "data": [
    {
      "table_index": 0,
      "headers": ["#", "Club", "Squad", "Age", "Market value"],
      "row_count": 20,
      "rows": [
        { "#": "1", "Club": { "text": "Real Madrid", "href": "..." }, ... }
      ]
    }
  ]
}
```

## Supported Pages

Works on any Transfermarkt page with tables, including:

- League overview / standings
- Club squad pages
- Player profiles with stats
- Transfer history pages
- Market value tables

## VirtuaFC integration — push straight to a PR

Instead of downloading each file and placing it under `data/{season}/` by hand,
the extension can commit scraped squads directly to the game repo and open a
pull request. CI then normalizes, validates, and posts a transfer diff (see
`docs/season-data-refresh.md`).

### One-time setup

1. Create a **fine-grained Personal Access Token** scoped to `pabloroman/virtua-fc`
   with **Contents: Read and write** and **Pull requests: Read and write**.
2. Open the extension popup → **⚙ GitHub Settings** and fill in:
   - **PAT** — the token from step 1 (stored in `chrome.storage.local`, never committed),
   - **Target season** — e.g. `2026` (drives the `data/2026/` paths and the `season-data/2026` branch),
   - **owner/repo** and **base branch** default to `pabloroman/virtua-fc` and `main`.
3. Click **Test GitHub connection**. It checks the values in the fields (not the
   saved ones, so a freshly pasted token can be verified first) against the same
   three things a push needs: the token itself, access to the repo, and a
   Contents read of the base branch. On success it reports the repo and the
   token's expiry date; write access is reported but cannot be proven without
   writing.

A season refresh runs the same check before it scrapes anything, so bad
credentials cost a couple of seconds instead of a full scrape.

### When a push fails on credentials

`401: Bad credentials` means the token itself was rejected — GitHub never got
as far as looking at the repo. Fine-grained PATs expire (30 days by default),
and GitHub revokes any token it finds committed somewhere, so a token that
worked last season is the usual cause. Generate a new one and save it.

`No access to owner/repo` means the token is valid but this repository is not in
its list — a fine-grained PAT reports a repo it cannot see as a 404, so it looks
like a missing repo rather than a missing grant. Check the token's *Repository
access* and that it has **Contents** and **Pull requests**.

From a terminal the same check is:

```bash
curl -sS -D- -o /dev/null \
  -H "Authorization: Bearer $GITHUB_TOKEN" \
  -H "X-GitHub-Api-Version: 2022-11-28" \
  https://api.github.com/repos/pabloroman/virtua-fc
```

`200` is fine (the `github-authentication-token-expiration` header gives the
expiry), `401` is a bad token, `404` is a token without access to this repo.

A push that sits on one line for minutes is not necessarily stuck: the popup now
names each phase (uploading, creating the commit, updating the branch, opening
the PR). If it does stall, open the service worker console
(`chrome://extensions` → the extension's **service worker** link) — the driver
logs there, and MV3 tears the worker down if the popup is closed while it runs.

`422: Update is not a fast forward` means the branch head moved while the push
was uploading — normally CI's `Canonicalize season {year} squad data` commit
from your *previous* push landing a few seconds later. The push now rebuilds its
commit on the new head and retries (up to three times), so this should no longer
surface; if it does, something is committing to the branch continuously.

### Push a single page

After scraping a supported page, click **Push to GitHub**:

- **League** (stadiums) and **cup/continental** (fixtures) pages → written to
  `data/{season}/{CODE}/teams.json` (the repo code is resolved from the
  Transfermarkt competition id via `season-config.js`).
- **Single club squad** pages → written to `data/{season}/{EUR|INT}/{id}.json`;
  pick the pool from the selector that appears.

The first push creates the `season-data/{season}` branch and opens a PR; later
pushes update the same PR. The popup links straight to it.

Continental participant lists (UCL/UEL/UECL/UEFASUP) keep their `pot` values
across a re-scrape: the extension reads the file already on the season branch
and carries each club's pot forward by transfermarkt id. Pots are not on any
Transfermarkt page — they are entered by hand — so a push that cannot read the
existing file aborts rather than overwriting them. A newly drawn club arrives
without a pot, which `app:validate-season` reports.

Club squad pages also record the club's country (the flag beside its league in
the page header, so Monaco reads as France — the federation UEFA protects on).
Unknown labels are left out rather than guessed; add them to `COUNTRY_CODES` in
`season-config.js`.

### Refresh every league at once

**SEASON REFRESH → Refresh all leagues** drives every league in `season-config.js`
(`batch: true`) for the target season — scraping each club's squad at a human
pace — then pushes them all in **one commit + PR**. Leave a Transfermarkt tab
open and active; **Stop** pauses cleanly between leagues. Cups and continental
participant lists are pushed individually with the per-page button (their pages
aren't part of the batch driver).

### Refresh the European pool

A continental participant from outside the eight scraped leagues has no squad
until it gets a `data/{season}/EUR/{id}.json` file of its own — 70 of 2025's 108
slots. **SEASON REFRESH → Refresh European pool** does that batch:

1. reads the UCL/UEL/UECL/UEFASUP participant lists and the league `teams.json`
   files already on the season branch,
2. takes every participant no league covers (deduplicated — the Super Cup's two
   clubs are also in the UCL/UEL lists),
3. scrapes each one's squad page and pushes them all in **one commit + PR**.

Run it **after** both the leagues and the participant lists are on the branch:
the club list is derived from what is there. If a league's squads are missing it
refuses rather than treating all ~108 participants as pool clubs; if a
participant list is missing it works with the ones that exist and names the rest
in the result.

Every target is re-scraped, including clubs that already have a pool file — a
pool file is last season's squad until this season overwrites it. As with the
league driver, one unreachable club is skipped and named in the summary rather
than sinking the run, and **Stop** pauses between clubs (nothing is pushed on a
pause). Clubs that need the `INT` pool are still pushed one page at a time.

**Expect it to take 15–25 minutes** (8 leagues × ~20 clubs, deliberately paced) and
to drive your active tab the whole way. **Keep the popup open** to watch progress —
the status poller lives in the popup, so closing it leaves you blind (the driver
keeps running, and reopening the popup re-attaches to the live progress). A league
that fails is skipped and named in the final summary rather than sinking the run.

> **After pulling changes to this folder, reload the extension** at
> `chrome://extensions` (↻ on the card). Chrome does not hot-reload the service
> worker, and a stale one silently ignores the season-refresh message.

Output is written in the repo's canonical form (2-space, sorted clubs/players,
`seasonID` injected), identical to `php artisan app:normalize-season`, so CI has
nothing to reformat.

## Files

| File | Role |
|------|------|
| `manifest.json` | MV3 manifest (adds `api.github.com` host permission for pushes). |
| `popup.html` / `popup.js` | UI: page scraper, GitHub settings, push, season refresh, batch positions. |
| `background.js` | Service worker: scraping orchestration + GitHub push + season-refresh driver. |
| `season-config.js` | Competition registry (repo code ↔ Transfermarkt id) and canonical `teams.json` serialization. |
| `github.js` | Minimal GitHub Git Data API client (atomic multi-file commit → branch → PR). |
| `content-*.js` | Per-page DOM extractors (competition, club, fixtures, cup teams, player positions). |

## Customization

Edit `content.js` to adjust the scraping logic if Transfermarkt changes their markup or if you need to target specific tables. Edit `season-config.js` to add competitions to the batch driver or fix a repo-code ↔ Transfermarkt-id mapping.
