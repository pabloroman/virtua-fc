# Yearly Season Data Refresh

How to move VirtuaFC's reference data to a new real-world season (e.g. 2025/26 →
2026/27) before a release. The engine is built for this: `Competition::season`
is a *base-season pointer* and fixture dates are offset by year as a career
progresses, so a new season is mostly a **data drop plus one config bump**.

Two ways to drop the data in: the **automated loop** (recommended — the browser
scraper pushes a PR that CI canonicalizes, validates, and annotates) or the
**manual runbook** below. Both end at the same place: a validated
`data/{season}/` folder ready to seed.

## Concepts

- **Base season** — the season newly-seeded reference data represents and the
  season new careers start in. Single source of truth: `config/season.php`
  (`config('season.current')`, env `GAME_SEASON`).
- **Data folder** — `data/{season}/{COMPETITION}/` holds `teams.json` (squads /
  participant lists) and `schedule.json` (match dates). Promotion/relegation is
  encoded purely by *which clubs appear in each competition's `teams.json`* — no
  code change is needed for membership changes.
- **`seasonID`** — each `teams.json` carries its own `seasonID`, which is the
  authority for the DB `competitions.season` column. It must match `GAME_SEASON`
  and the folder name.
- **Games are self-contained snapshots** — squads are copied into `game_players`
  at creation, so re-seeding reference data never corrupts saved games' rosters.

## Automated refresh loop (preseason)

During preseason, clubs buy and sell players for weeks. Rather than re-dropping
files by hand on every transfer, let the scraper drive a single living PR:

1. **Scaffold once** at the start of preseason: `php artisan app:scaffold-season
   2026` (creates folders + bootstraps schedules). Commit it to a
   `season-data/2026` branch and open a PR.
2. **Re-scrape whenever you like.** The browser scraper writes the squad files
   for *all* leagues into `data/2026/` and pushes to `season-data/2026` (whole
   leagues are overwritten — that is fine, see below). The same PR updates.
3. **CI does the busywork** (`.github/workflows/season-data.yml`, triggered by
   any change under `data/**`):
   - runs `app:normalize-season 2026` and commits the canonical form back to the
     branch (forces `seasonID`, sorts clubs/players for clean diffs),
   - runs `app:validate-season 2026` as a hard merge gate, and
   - posts an `app:diff-season 2026` transfer summary (signings / departures /
     club movements vs last season) as a sticky PR comment.
4. **Let it accumulate.** Because games snapshot squads at creation, the data
   only has to be right *at release*. Keep force-pushing scrapes to the PR
   through preseason; skim the diff comment each time; **merge when you're ready
   to cut the release**, then follow the seed steps below.

Whole-league re-scrapes stay diff-friendly because normalization sorts clubs by
transfermarkt id and players by player id — a single transfer shows up as one
add/remove line, not a reshuffled roster.

> The scraper only needs to write valid JSON to the right `data/2026/{COMP}/`
> path; CI's normalize step is the formatting authority, so the extension does
> not have to match byte-for-byte. The push uses a fine-grained PAT scoped to
> this repo (Contents + Pull requests, read/write).

## Helper commands

| Command | What it does |
|---------|--------------|
| `app:scaffold-season {season}` | Create folders, bootstrap schedules from last season. |
| `app:normalize-season {season} [--check]` | Force `seasonID`, sort clubs/players, canonical 2-space formatting. `--check` verifies without writing (the CI gate). Idempotent. |
| `app:validate-season {season}` | Read-only completeness/correctness gate (non-zero exit on any problem). |
| `app:diff-season {season} [--from=] [--format=md]` | Report signings, departures, and club movements vs a previous season. |
| `app:seed-reference-data [--fresh] [--country=]` | Seed competitions, teams, fixtures, templates from `data/{season}/`. |

## Runbook (e.g. releasing 2026/27)

1. **Bump the base season.** Set `GAME_SEASON=2026` in the environment (and
   `.env`), and update the default in `config/season.php`. (It ships set to
   `2025` so the engine keeps using `data/2025/` until the new season is ready.)

2. **Scaffold the data folder** (creates dirs, bootstraps `schedule.json` for
   every competition by shifting last season's dates forward one year):

   ```bash
   php artisan app:scaffold-season 2026
   ```

   It prints a checklist of the `teams.json` / pool files the scraper must
   provide. Real fixture dates can replace the bootstrapped schedules later.

3. **Drop in scraped squads.** From the browser scraper, write into
   `data/2026/`:
   - `data/2026/{LEAGUE}/teams.json` for each playable + foreign league
     (ESP1, ESP2, ESP3A, ESP3B, ENG1, DEU1, FRA1, ITA1) — clubs + squads,
     reflecting real promotion/relegation. **Set `"seasonID": "2026"`.**
   - `data/2026/{CUP}/teams.json` participant lists (ESPCUP, ESPSUP).
   - `data/2026/{UCL,UEL,UECL,UEFASUP}/teams.json` participant lists.
   - `data/2026/EUR/{id}.json` and `data/2026/INT/{id}.json` pool teams.
   - Append any new players to `data/players/player_positions_ES.json`
     (secondary positions; keyed by player id, not season-scoped).

   `ESP3PO` (Primera RFEF playoff) is intentionally schedule-only — no
   `teams.json`; its bracket is generated per-game.

4. **Normalize** (forces every `seasonID` to `2026` and sorts clubs/players so
   re-scrapes diff cleanly — so you can skip the manual `seasonID` edits above):

   ```bash
   php artisan app:normalize-season 2026
   ```

   Optionally review what changed vs last season:
   `php artisan app:diff-season 2026`.

5. **Validate before seeding** (read-only gate; non-zero exit on any problem):

   ```bash
   php artisan app:validate-season 2026
   ```

   Checks every competition has the expected data, `seasonID` matches,
   transfermarkt ids resolve, and each round-robin league's schedule has exactly
   `2 × (teams − 1)` rounds (the invariant the fixture generator enforces).

6. **Seed a fresh database** (wipes prior reference data and games, then seeds
   2026 and auto-generates player templates for season 2026):

   ```bash
   php artisan app:seed-reference-data --fresh
   php artisan config:clear
   ```

   Targeted re-import of one country later:

   ```bash
   php artisan app:seed-reference-data --country=ES
   php artisan app:refresh-player-templates --season=2026 --country=ES
   ```

## Notes & caveats

- **Re-seed on a fresh DB / new cohort.** `competitions.season` is one row per
  competition id, so flipping the base season re-points it for everyone. Do not
  re-seed an active production DB mid-season — a live game would then compute a
  negative year offset for its fixtures.
- **Year boundary.** A league season spans Aug → Jun; the scaffolder shifts each
  absolute date by one year, preserving the crossover (Aug 2026 → May 2027).
- **World Cup (WC2026) is out of scope.** It is a fixed real-world tournament
  under `data/2025/WC2026/` with its own commands and is intentionally *not*
  tied to the career base season.
