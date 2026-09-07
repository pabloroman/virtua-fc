# Sofascore image downloader

Bulk-downloads player avatars from Sofascore's image CDN
(`https://img.sofascore.com/api/v1/player/{ID}/image`), keyed by **Sofascore player
ID**, and bundles them into a `.zip` — ready to drop onto the game's `assets` disk.

The game self-hosts avatars: `GamePlayer::getImageUrlAttribute()`
(`app/Models/GamePlayer.php`) resolves each photo to `players/{sofascore_id}.webp` on
the `assets` disk (rendered by `resources/views/components/player-banner.blade.php`,
with a fallback avatar when the file is missing). This tool produces those files.

## Prefer `app:fetch-player-photos`

**The normal way to get photos is the artisan command:**

```bash
php artisan app:fetch-player-photos            # current season
php artisan app:fetch-player-photos --season=2026 --force
```

It reads the Sofascore ids straight from `game_player_templates`, writes
`players/{sofascore_id}.webp` into the assets disk, skips what is already there
and counts 404s (players Sofascore has no photo for) separately from real
failures. It is the sibling of `app:fetch-team-crests`.

This browser tool remains only for the case the command cannot serve: fetching
photos for a list of ids that is **not** in the database.

## About the 403 (this tool used to be the only option)

The image CDN was believed to reject anything that was not a sofascore.com tab.
**That is not true** — re-tested 2026-09-07, `https://img.sofascore.com/api/v1/player/{ID}/image`
returns `200` to plain `curl` with no headers at all, which is why the artisan
command works server-side.

The 403 is real for the **search** API (`/api/v1/search/...`), which is why
[`../sofascore-id-finder/`](../sofascore-id-finder/) genuinely does still have to
run in a console on sofascore.com. Images do not.

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

Where to get the IDs: `data/sofascore_ids.json` (built by
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
