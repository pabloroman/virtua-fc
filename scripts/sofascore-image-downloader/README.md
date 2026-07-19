# Sofascore image downloader

Bulk-downloads player avatars from Sofascore's image CDN
(`https://img.sofascore.com/api/v1/player/{ID}/image`), keyed by **Sofascore player
ID**, and bundles them into a `.zip` — ready to drop onto the game's `assets` disk.

The game self-hosts avatars: `GamePlayer::getImageUrlAttribute()`
(`app/Models/GamePlayer.php`) resolves each photo to `players/{sofascore_id}.webp` on
the `assets` disk (rendered by `resources/views/components/player-banner.blade.php`,
with a fallback avatar when the file is missing). This tool produces those files.

## Why it runs in the browser (and only on sofascore.com)

The CDN returns `access-control-allow-origin: *`, so JavaScript is *allowed* to read the
image bytes — **but** it responds `403` to any request whose referer/origin isn't
sofascore.com (bot protection). That means:

- A standalone `.html` file, a `file://` page, `curl`, or a fetch from any other site → **403**.
- The identical `fetch` run **from a tab already on sofascore.com → 200** with real bytes.

So the tool is a script you run inside a sofascore.com tab. Missing IDs return a clean
`404` (skipped and logged, never a hard failure).

The zip is assembled in-page with a tiny built-in STORE-only zip writer (no compression —
the images are already compressed). We deliberately don't pull in JSZip: loading a script
from a CDN would be blocked by sofascore.com's Content-Security-Policy.

## How to run (console — recommended)

1. Open <https://www.sofascore.com> in Chrome.
2. Open DevTools → **Console** (`⌥⌘J` on macOS).
3. First time only, if Chrome asks: type `allow pasting` and press Enter.
4. Paste the entire contents of [`download-images.js`](./download-images.js) and press Enter.
   A small panel appears in the top-right corner.
5. Paste your Sofascore IDs into the textarea and click **Download ZIP**.

## Input formats

The textarea accepts either:

- **A list of Sofascore IDs** in any format — newline, comma, space, or tab separated.
  Non-numeric junk is ignored and duplicates are removed automatically.
- **A pasted `sofascore_ids.json` map** — the tool detects JSON and takes the unique
  **values** (the file is a `{transfermarkt_id: sofascore_id}` map, so the values are the
  Sofascore IDs).

Where to get the IDs: `data/{season}/sofascore_ids.json` (built by
`php artisan app:build-sofascore-id-map`). The **values** of that map are what this tool
wants. Don't paste all ~83k at once — filter to the players you actually need (e.g. a
league or the season's new arrivals); use **Max per zip** to split large runs.

## Output

- One `sofascore-images.zip` containing `{sofascore_id}.webp` for every ID that returned an
  image. (The CDN serves a mix of webp and jpeg; everything is named `.webp` because that's
  the extension the game expects — the browser renders by content, so it displays correctly
  either way.)
- `_failures.txt` inside the zip lists every ID that 404'd or errored (`id<TAB>reason`), so
  you can re-run over just those.
- Large lists split into `sofascore-images-part-01.zip`, `-part-02.zip`, … (Chrome may ask
  to "allow multiple downloads").

Extract the zip's images into the `assets` disk under `players/` and the game will serve
them via `GamePlayer::image_url`.

## Options

- **Concurrency** — simultaneous fetches (default 6). Raise for speed, lower if you get
  rate-limited.
- **Max per zip** — entries per archive before splitting into parts (default 1000).

## Bookmarklet (optional)

For repeat use you can save it as a bookmarklet instead of pasting each time: wrap the whole
file in `javascript:(function(){ …file contents… })()` and save it as a bookmark's URL, then
click it while on sofascore.com. Console paste is the recommended path — some pages' CSP can
interfere with `javascript:` bookmarklets, whereas console paste always works.
