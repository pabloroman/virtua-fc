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
> this repo (Contents + Pull requests, read/write). Configure it once in the
> extension popup (**⚙ GitHub Settings**) and drive a full refresh from
> **Season Refresh → Refresh all leagues** — see
> `scripts/transfermarkt-scraper/README.md`.

## Helper commands

| Command | What it does |
|---------|--------------|
| `app:scaffold-season {season}` | Create folders, bootstrap schedules from last season (dates shifted by whole weeks, so weekdays hold). |
| `app:normalize-season {season} [--check]` | Force `seasonID`, sort clubs/players, canonical 2-space formatting. `--check` verifies without writing (the CI gate). Idempotent. |
| `app:validate-season {season}` | Read-only completeness/correctness gate (non-zero exit on any problem). Database-free, so CI can run it without Postgres. |
| `app:diff-season {season} [--from=] [--format=md]` | Report signings, departures, and club movements vs a previous season. |
| `app:seed-reference-data [--fresh] [--country=]` | Seed competitions, teams, fixtures, templates from `data/{season}/`. |

## Runbook (e.g. releasing 2026/27)

1. **Bump the base season.** Set `GAME_SEASON=2026` in the environment (and
   `.env`), and update the default in `config/season.php`. (It ships set to
   `2025` so the engine keeps using `data/2025/` until the new season is ready.)

2. **Scaffold the data folder** (creates dirs, bootstraps `schedule.json` for
   every competition by shifting last season's dates forward a whole number of
   weeks):

   ```bash
   php artisan app:scaffold-season 2026
   ```

   It prints a checklist of the `teams.json` / pool files the scraper must
   provide. Real fixture dates can replace the bootstrapped schedules later.

3. **Drop in scraped squads.** From the browser scraper, write into
   `data/2026/`:
   - `data/2026/{LEAGUE}/teams.json` for each playable + foreign league
     (ESP1, ESP2, ESP3A, ESP3B, ENG1, DEU1, FRA1, ITA1, POR1, NED1) — clubs + squads,
     reflecting real promotion/relegation. **Set `"seasonID": "2026"`.**
   - `data/2026/{CUP}/teams.json` participant lists (ESPCUP, ESPSUP).
   - `data/2026/{UCL,UEL,UECL,UEFASUP}/teams.json` participant lists. These are
     independent of the transfer window — the draws are known before it shuts —
     so they can go in first. Scrape them from the **participants page**
     (`/teilnehmer/pokalwettbewerb/...`), not the fixture list: the fixture list
     spans qualifying, so it returns every club knocked out on the way in too.
     Their squads cannot go in first: every participant outside the eight
     scraped leagues needs an `EUR` pool file (70 of 108 slots in 2025) —
     **Season Refresh → Refresh European pool** derives that list from the
     participant lists and league squads already on the branch and scrapes them
     in one batch, so run it after both are pushed. Add `pot` by hand for a
     true-to-life first-season draw; a re-scrape now preserves it.
   - `data/2026/EUR/{id}.json` and `data/2026/INT/{id}.json` pool teams.
   - Secondary positions (`data/players/player_positions_ES.json`, keyed by
     player id and *not* season-scoped) are topped up by their own two
     commands — see step 3b.

   `ESP3PO` (Primera RFEF playoff) is intentionally schedule-only — no
   `teams.json`; its bracket is generated per-game.

3b. **Top up secondary positions.** Ask which players the scraper has never
   been pointed at, which writes the batch list the extension reads:

   ```bash
   php artisan app:list-missing-positions 2026
   ```

   It prints per-competition coverage and writes
   `scripts/transfermarkt-scraper/player-ids-todo.csv`. In the extension pick
   **Pending positions** under *Batch player positions*, Start, then Download
   JSON and merge it back:

   ```bash
   php artisan app:merge-player-positions ~/Downloads/player_positions.json \
       --attempted=scripts/transfermarkt-scraper/player-ids-todo.csv
   ```

   Pass `--attempted` and commit the updated ledger CSV alongside the JSON.
   The scraper only returns players that *have* a secondary position, so the
   ledger is the only record that the rest were looked at; without it they come
   back as "pending" every season. The merge is a union — entries for players
   who have left the league are still live data for them elsewhere, so nothing
   is pruned.

   Use `--competition=ENG1` (repeatable) with `--suffix` to cover a foreign
   league in its own `player_positions_{SUFFIX}.json`; the template service
   globs every such file.

4. **Normalize** (forces every `seasonID` to `2026`, sorts clubs/players so
   re-scrapes diff cleanly, and backfills each club's `country` — so you can skip
   the manual `seasonID` edits above):

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

   European competitions get extra rules, because their participant lists only
   *link* clubs seeded elsewhere:

   - every participant needs a literal `id` and squad data somewhere in the
     season folder — a league `teams.json` or an `EUR`/`INT` pool file. Without
     it the seeder drops the club and the competition ends up with no fixtures
     at all, silently;
   - UCL/UEL/UECL need exactly 36 clubs (UEFASUP, a two-club knockout, is
     exempt);
   - seeding pots are optional, but all-or-nothing: give every club a `pot`
     forming four pots of nine, or none at all. A partial set usually means a
     re-scrape overwrote hand-entered pots. With no pots the draw seeds itself
     by squad market value, exactly as it does from the second season onward.

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

## Releasing to a database with live saves

Step 6 above (`--fresh`) is the fresh-database path. **On production, never use
`--fresh`**: it deletes `teams`, and the career tables that outlive a game hold
RESTRICT foreign keys to it (`manager_stats`, `manager_trophies`,
`manager_job_histories`, `manager_job_offers`, `manager_season_records`,
`tournament_summaries`, `user_squad_career_records`). The delete fails outright
on a database with any career history, and teams are re-inserted with fresh
UUIDs, so even a successful run would strand every leaderboard row.

Seed without `--fresh` instead. Teams are matched by `transfermarkt_id` and
updated in place, so team UUIDs — and everything keyed to them — survive, and
`competition_teams` is keyed `(competition_id, team_id, season)`, so the new
season's membership is *added* alongside the old rows rather than replacing
them. Reads are scoped to each competition's own season
(`CompetitionTeam::SEASON_MATCHES_COMPETITION`), so those old rows stay
queryable for the saves that still need them without leaking into the new
season's team lists. In order:

1. Snapshot the database.
2. Deploy the code, running migrations **before** the env var changes. Careers
   are pinned to their own base season by `games.base_season` (see below); that
   column has to exist and be backfilled before the global season moves.
3. Set `GAME_SEASON=2026`.
4. `php artisan app:seed-reference-data` — no `--fresh`.
5. `php artisan app:refresh-player-templates --season=2026`.
6. `php artisan config:clear`.

What live saves do notice: club names are canonicalised (see
`App\Support\ClubNames`), and `teams.stadium_name`, `stadium_seats`, `image`
and `colors` refresh in place. Per-game state is untouched — stadium upgrades
live in `game_stadiums`, and reputation in `team_reputations`, both keyed by
`game_id`. Newly promoted clubs are inserted as new rows but never enter an
existing save, whose competition membership is its own `competition_entries`.

Verify afterwards by taking an existing save through a cup draw and a season
transition, not just by loading its squad.

## Adding a new country

A season refresh moves existing leagues forward; adding a *country* is a
separate, one-off job. `config/countries.php` is the master registry — anything
keyed off it (career-mode team selection, seeding, UEFA qualification, synthetic
simulation of leagues the user is not in) picks the country up with no further
code change. What does need writing, in order:

1. **A competition config class** in `app/Modules/Competition/Configs/` —
   TV-revenue curve, season goals, award lang keys and standings zones. Copy the
   closest-shaped existing league (`Ligue1Config` for an 18-club division). A
   country with no `config_class` still works, but falls back to
   `DefaultLeagueConfig`'s generic money.
2. **A `config/countries.php` block** — `tiers`, `continental_slots`,
   `continental_competitions` and `support`. Foreign single-tier countries carry
   `domestic_cups => []`, `promotions => []` and `cup_winner_slot => null`. Add
   the new league to the other countries' `support.transfer_pool` too, or its
   players are untradeable everywhere else.
3. **Display names** — `SHORT_NAMES` and `ABBREVIATIONS` in `app/Models/Competition.php`.
4. **Translations** in both `lang/es/` and `lang/en/` for the new award keys.
5. **A kit-colour provider** in `app/Support/TeamColors/`, registered in both
   `TeamColors::teams()` and `TeamColors::allGrouped()`.
6. **Club profiles** in `database/seeders/ClubProfilesSeeder.php` — reputation,
   fan loyalty, preferred formation. Unlisted clubs silently become
   local-reputation, neutral-loyalty; `app:validate-season -v` lists them.
7. **Naming-rights brands** in `config/commercial.php` keyed to the new country,
   or its clubs can only sign the country-agnostic GLOBAL sponsors.
8. **A `data/{season}/{CODE}/schedule.json`** — `app:scaffold-season` can only
   shift a *previous* season's calendar, and a new league has none, so write it
   by hand (copy a same-sized league's file and move the dates). The validator
   requires exactly `2 × (teams − 1)` rounds.
9. **A `scripts/transfermarkt-scraper/season-config.js` entry** mapping the repo
   code to the Transfermarkt competition id, then scrape `teams.json`.

Afterwards: clubs promoted out of the `EUR` pool into the new league should have
their `data/{season}/EUR/{id}.json` deleted, so the league file is the single
squad source. Clubs from that country playing *below* the new top flight must
keep their pool file, or the European participant lists stop validating.

## Notes & caveats

- **Games are pinned to the season they were created in.** `games.base_season`
  records which `data/{season}/` folder a save reads its schedules from, and is
  the origin its fixture dates are offset against (`season - base_season`
  years). Everything game-scoped reads it rather than `Competition::season` or
  `config('season.current')`, both of which move for every save at once when
  reference data is refreshed. Two consequences: a career started before a
  release keeps playing its original calendar, and **old `data/{season}/`
  folders can never be deleted** — `data/2025/` is load-bearing for every save
  created before the 2026/27 release.
- **Year boundary.** A league season spans Aug → Jun; the scaffolder shifts every
  absolute date by the same whole number of weeks — a year rounded up, so 371
  days (53 weeks) for a one-year bump. That preserves the crossover (Aug 2026 →
  May 2027) *and* every fixture's weekday, so leagues stay on Saturday/Sunday,
  European nights on Tuesday–Thursday and the cups midweek. A plain +1 year would
  not: 365 days is 52 weeks plus a day, sliding the whole season one weekday over.
- **World Cup (WC2026) is out of scope.** It is a fixed real-world tournament
  under `data/2025/WC2026/` with its own commands and is intentionally *not*
  tied to the career base season.
