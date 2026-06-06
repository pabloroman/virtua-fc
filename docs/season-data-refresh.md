# Yearly Season Data Refresh

How to move VirtuaFC's reference data to a new real-world season (e.g. 2025/26 →
2026/27) before a release. The engine is built for this: `Competition::season`
is a *base-season pointer* and fixture dates are offset by year as a career
progresses, so a new season is mostly a **data drop plus one config bump**.

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

## Runbook (e.g. releasing 2026/27)

1. **Bump the base season.** Set `GAME_SEASON=2026` in the environment (and
   `.env`). Default in `config/season.php` is already `2026`.

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

4. **Validate before seeding** (read-only gate; non-zero exit on any problem):

   ```bash
   php artisan app:validate-season 2026
   ```

   Checks every competition has the expected data, `seasonID` matches,
   transfermarkt ids resolve, and each round-robin league's schedule has exactly
   `2 × (teams − 1)` rounds (the invariant the fixture generator enforces).

5. **Seed a fresh database** (wipes prior reference data and games, then seeds
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
