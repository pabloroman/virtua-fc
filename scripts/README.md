# Promo Video Recording

Scripts for recording a ~60-second, 1920x1080 gameplay video of VirtuaFC touring all key screens of a La Liga career mode game. The recording is fully scripted via Playwright for repeatable, pixel-perfect takes.

## Prerequisites

- **PHP / Laravel** environment working (`php artisan` commands run successfully)
- **Node.js** (v18+)
- **Playwright + Chromium** installed:
  ```bash
  npm install -D playwright
  npx playwright install chromium
  ```
- **`composer dev` running** in a separate terminal — this starts the web server, queue worker, and Vite dev server. The queue worker is essential for game setup jobs and post-match processing.
- **(Optional)** `ffmpeg` for converting the output `.webm` to `.mp4`

## Step 1: Set Up the Game

The setup script seeds a full La Liga database, creates a career game as Real Madrid, and advances it to matchday 8 so there are standings, results, and form data to show.

```bash
bash scripts/setup-promo-game.sh
```

This takes 1-3 minutes and does the following:

1. **Seeds reference data** (`php artisan app:seed-reference-data --fresh`) — full production profile with La Liga, Segunda Division, Copa del Rey, Champions League, etc. Creates a default user `test@test.com` / `password`.
2. **Creates a career game** with Real Madrid via `GameCreationService`.
3. **Processes the setup job** (`queue:work --stop-when-empty`) — generates fixtures, standings, budgets, etc.
4. **Skips onboarding flows** — disables the welcome screen, onboarding wizard, and pre-season so the video starts in the main game UI.
5. **Simulates 8 matchdays** — creates realistic standings, results, and player form.
6. **Outputs `GAME_ID` and `COMPETITION_ID`** — copy these for the next step.

Example output:
```
============================================
  Promo game setup complete!

  GAME_ID=a1b2c3d4-e5f6-7890-abcd-ef1234567890
  COMPETITION_ID=f9e8d7c6-b5a4-3210-fedc-ba0987654321

  Run the recording script:
  GAME_ID=a1b2c3d4... COMPETITION_ID=f9e8d7c6... node scripts/record-promo-video.mjs
============================================
```

> **Note:** If the setup fails at the queue step, make sure `composer dev` is running in another terminal. The `SetupNewGame` job needs the queue worker.

## Step 2: Record the Video

Pass the IDs from step 1 as environment variables:

```bash
GAME_ID=<uuid> COMPETITION_ID=<uuid> node scripts/record-promo-video.mjs
```

### Environment Variables

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `GAME_ID` | Yes | — | UUID of the game created in step 1 |
| `COMPETITION_ID` | No | — | UUID of La Liga competition (for standings page). Falls back to clicking the first competition link in the nav. |
| `BASE_URL` | No | `http://virtuafc.test` | Base URL of your local VirtuaFC instance |

### What It Records

The script runs headless Chromium at 1920x1080 and tours these screens:

| # | Screen | ~Duration | What you see |
|---|--------|-----------|--------------|
| 1 | Dashboard | 4s | Game card with Real Madrid crest, season info |
| 2 | Game Home | 6s | Next match preview, upcoming fixtures, scrolls to standings/notifications |
| 3 | Squad | 6s | Player table, scrolls through all positions and KPIs |
| 4 | Lineup | 5s | Formation pitch with tactical panel |
| 5 | Advance | 2s | Clicks "Continuar" in the header, triggers match simulation |
| 6 | Live Match | ~17s | Match plays at 4x speed with animated events, goals, cards |
| 7 | Results | 4s | Post-match results page for the matchday |
| 8 | Standings | 5s | Full La Liga table, scrolls through |
| 9 | Transfers | 5s | Transfer market / scouting UI |
| 10 | Finances | 4s | Budget projections and financial overview |

**Total:** approximately 60 seconds.

### How It Works

- **Authentication happens off-camera.** The script logs in via a non-recorded browser context, saves the session cookies, then creates a new recorded context with those cookies. The video starts already authenticated on the dashboard.
- **Live match speed** is set to 4x via `localStorage` before any page loads, so the match plays through in ~15 seconds.
- **Match completion detection** uses Alpine.js internals (`_x_dataStack[0].phase === 'full_time'`) to know when the match has ended.
- **Post-match processing** polls `processingReady` (Alpine data) to wait for the queue worker to finish career actions before clicking "Continue".
- **Smooth scrolling** uses a custom `requestAnimationFrame` loop with cubic ease-in-out interpolation for natural-looking scroll animations.

> **Important:** The queue worker (`composer dev`) must be running during recording. The live match finalization dispatches queued jobs, and the script waits for `processingReady` to become `true` before clicking the finalize button. Without the queue worker, this will time out after 30 seconds.

## Step 3: Convert to MP4 (Optional)

Playwright outputs `.webm` (VP8). For wider compatibility or editing, convert to MP4:

```bash
ffmpeg -i videos/virtuafc-promo.webm \
  -c:v libx264 -preset slow -crf 18 -pix_fmt yuv420p \
  videos/virtuafc-promo.mp4
```

- `-crf 18` — high quality (lower = higher quality, 18 is visually lossless)
- `-preset slow` — better compression at the cost of encoding time

## Output

Videos are saved to the `videos/` directory (gitignored):

```
videos/
  virtuafc-promo.webm   ← Raw Playwright output
  virtuafc-promo.mp4    ← Optional ffmpeg conversion
```

## Troubleshooting

### "Recording failed: Timeout exceeded" on live match

The queue worker is not running or not processing jobs fast enough. Make sure `composer dev` is active. You can also run a dedicated queue worker in another terminal:
```bash
php artisan queue:listen --tries=1
```

### "ERROR: Failed to create game" during setup

The seeding step may have failed silently. Run `php artisan app:seed-reference-data --fresh` manually and check for errors. Ensure your database is accessible.

### Video is blank or shows login page

The authentication step failed. Verify that:
- The app is running at the `BASE_URL` (default: `http://virtuafc.test`)
- The user `test@test.com` exists with password `password`
- Vite dev server is running (for CSS/JS assets)

### Video quality is low or resolution is wrong

Playwright records at the viewport size. The scripts use `1920x1080` for both viewport and video size. If the output looks wrong, check that no system-level scaling is interfering (headless mode should avoid this).

### Match never reaches full time

The Alpine.js live match component may not have initialized. Check that the Vite dev server is running (`npm run dev` or `composer dev`) so JavaScript assets are served correctly. The script waits up to 60 seconds for `full_time`, which is more than enough at 4x speed (~15s).

### Results page shows 404

The results route uses `ESP1` as the competition code and matchday `9` (since the game was at matchday 8, the played match is matchday 9). If the matchday numbering differs, edit line 237 in `record-promo-video.mjs`.

## Customization

### Change the team
Edit `setup-promo-game.sh` line 25 — change `'Real Madrid'` to any La Liga team name (must match the `teams` table exactly).

### Adjust screen timings
Each `pause()` call in `record-promo-video.mjs` controls how long the camera stays on each screen. Increase values for longer takes, decrease for a faster tour.

### Change match speed
The script sets 4x speed via `localStorage.setItem('liveMatchSpeed', '4')`. Change `'4'` to `'2'` for 2x speed (~30s match) or `'1'` for 1x (~60s match).

### Skip the live match
To skip the match and jump straight to results, replace the advance/live-match/finalize section with a direct call to `game:simulate` and navigate to the results page.

### Add more screens
Add new sections between the existing ones following the pattern:
```javascript
console.log('N. Screen: Calendar');
await page.goto(`${BASE_URL}/game/${GAME_ID}/calendar`, { waitUntil: 'networkidle' });
await pause(4000);
await smoothScrollToBottom(page, 1500);
await pause(1500);
```
