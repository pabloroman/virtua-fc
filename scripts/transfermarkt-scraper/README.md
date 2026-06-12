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

### Push a single page

After scraping a supported page, click **Push to GitHub**:

- **League** (stadiums) and **cup/continental** (fixtures) pages → written to
  `data/{season}/{CODE}/teams.json` (the repo code is resolved from the
  Transfermarkt competition id via `season-config.js`).
- **Single club squad** pages → written to `data/{season}/{EUR|INT}/{id}.json`;
  pick the pool from the selector that appears.

The first push creates the `season-data/{season}` branch and opens a PR; later
pushes update the same PR. The popup links straight to it.

### Refresh every league at once

**SEASON REFRESH → Refresh all leagues** drives every league in `season-config.js`
(`batch: true`) for the target season — scraping each club's squad at a human
pace — then pushes them all in **one commit + PR**. Leave a Transfermarkt tab
open and active; **Stop** pauses cleanly between leagues. Cups, continental
participant lists, and EUR/INT pools are pushed individually with the per-page
button (their pages aren't part of the batch driver).

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
