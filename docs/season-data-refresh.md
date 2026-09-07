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
| `app:build-sofascore-id-map` | Rebuild the player-photo crosswalk `data/sofascore_ids.json` from `data/raw/people.csv` + `data/sofascore_ids_overrides.csv`. Season-independent — run it after editing the overrides, not every season. |
| `app:list-unmapped-players [--season=] [--limit=] [--out=]` | Emit the players with no Sofascore id as JSON, to feed `scripts/sofascore-id-finder/`. |
| `app:fetch-player-photos [--season=] [--force]` | Download player photos into the assets disk, keyed by Sofascore id. Sibling of `app:fetch-team-crests`. |

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

6. **Refresh the player-photo crosswalk** — only if step 5 warned about
   coverage.

   Photos resolve `transfermarkt_id → sofascore_id → {CDN}/players/{id}.webp`
   (`GamePlayer::getImageUrlAttribute`). The map lives at
   `data/sofascore_ids.json` and is **deliberately not season-scoped** — both
   ids are permanent, and a per-season copy is exactly how the 2026 refresh
   shipped with no map at all and lost every player photo silently.

   So there is normally nothing to do here per season. When the validator says
   coverage dropped, the new season added players the crosswalk has never
   covered — see below for what that does and doesn't mean.

   ### Where the crosswalk comes from, and why it can't be refreshed

   `data/raw/people.csv` (untracked, ~63 MB) is the **Reep v0** register —
   <https://github.com/withqwerty/reep>, `data/people.csv`, CC0. Its
   `key_transfermarkt` + `key_sofascore` columns are the whole basis of the map.

   **That source is frozen.** Reep v0 stopped at data version `2026.25`
   (21 June 2026) and its README states the data files no longer change. The
   living **Reep v1** (<https://data.reep.football/releases/latest.json>, weekly)
   replaced the wide table with a long `bridges.csv.gz`
   — and **dropped Sofascore ids from the public release entirely**. Its provider
   list is opta, wyscout, transfermarkt, api_football, skillcorner, sportmonks,
   fm, espn, fifa, scisports, eafc, besoccer, fbref_dsg, uefa, capology,
   soccerdonna, statsbomb, jleague, fbref, national_football_teams,
   second_spectrum, understat, rsssf, clubelo. No Sofascore.

   The copy in `data/raw/` is already the final v0 content — re-downloading it
   yields **zero** new pairs. Re-importing is not the fix for low coverage.

   Reep's Sofascore ids come from Wikidata (property `P12302`), so an uncovered
   player is one Wikidata hasn't linked. As of the 2026 dataset that's 1,835 of
   7,424: 615 are in `people.csv` with a blank `key_sofascore`, and 1,220 aren't
   in the register at all (lower divisions and youth, mostly).

   ### Closing the gap

   Uncovered players are mapped in `data/sofascore_ids_overrides.csv` (tracked,
   layered on top, wins over the base map, survives every re-import).
   `scripts/sofascore-id-finder/` does the lookup rather than you doing it by
   hand — it searches Sofascore for each name and confirms the hit by exact date
   of birth, so a same-name player can't be matched by mistake:

   ```bash
   php artisan app:list-unmapped-players --out=unmapped.json   # --limit=25 to trial it
   # paste scripts/sofascore-id-finder/find-ids.js into a DevTools console on
   # sofascore.com, then paste unmapped.json into its panel (see its README)
   cat sofascore_ids_overrides_additions.csv >> data/sofascore_ids_overrides.csv
   php artisan app:build-sofascore-id-map
   ```

   It resolved 10 of a 15-player sample of the real 2026 gap; the rest were
   genuinely absent from Sofascore. Whatever it can't confirm comes back as
   commented rows to fill in by hand — the id is the trailing number in a
   Sofascore profile URL, `sofascore.com/player/{slug}/{id}`.

   Re-run the `sofascore_ids` backfill migration afterwards to push the new ids
   into templates and existing saves.

   Newly mapped players still need their photo, or the CDN 404s and they keep
   the default avatar:

   ```bash
   php artisan app:fetch-player-photos --season=2026
   ```

   Unlike the search API, the Sofascore *image* CDN answers ordinary server-side
   requests, so this needs no browser. It writes `players/{sofascore_id}.webp`
   into the assets disk, skips what is already there, and counts 404s — players
   Sofascore has no photo for — separately from real failures.
   `scripts/sofascore-image-downloader/` is now only for ids that aren't in the
   database.

   That only puts the files on the machine you ran it on. `public/players/` is
   gitignored — the photos live in the Cloudflare R2 bucket the CDN serves — so
   publish them:

   ```bash
   export R2_ACCOUNT_ID=... R2_ACCESS_KEY_ID=... R2_SECRET_ACCESS_KEY=...
   scripts/upload-assets-to-r2.sh players --dry-run   # check first
   scripts/upload-assets-to-r2.sh players
   ```

   Use an R2 API token scoped to *Object Read & Write* on that one bucket. The
   script's header has the details, and it also handles `crests`.

7. **Seed a fresh database** (wipes prior reference data and games, then seeds
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

Step 7 above (`--fresh`) is the fresh-database path. **On production, never use
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
   `continental_competitions` and `support`, plus `from_season` (see the next
   section). Add the new league to the other countries' `support.transfer_pool`
   too, or its players are untradeable everywhere else.
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

## Introducing a country or a cup in a future season

`config/countries.php` is enumerated by `app:seed-reference-data` and
`app:validate-season`, both of which demand a `data/{season}/{CODE}/` folder for
every competition they find. So adding one unconditionally invalidates every
earlier season the moment the config lands: that season can no longer be seeded
or validated, and the release can only be undone by reverting the merge.

Declare `from_season` instead, on the country block, the `domestic_cups` entry
or the `support.transfer_pool` entry:

```php
'PT' => [
    'name' => 'Portugal',
    'from_season' => '2026',
    // ...
],
```

The competition is then invisible to the seeder and the validator until that
season, so 2025 keeps working while 2026's data sits on `main` unused, and
`GAME_SEASON` stays a switch you can move in both directions.

A supercup needs no key of its own — it is hidden while the cup it is contested
between is. Runtime callers need none either: a save is never handed a
competition added after it began, because the season processors skip a cup with
no `competitions` row and one the game holds no field for.

## Fixture clashes

`app:validate-season` also checks that no club is booked for two matches on one
date. It is a real failure mode, not a theoretical one: ESP1 and ENG1 once had a
midweek round on the same day as Europa League matchday 1, and every Spanish or
English club in that competition was scheduled twice. Nothing catches it at
runtime — the matchday service collects every unplayed match on the earliest
date and the orchestrator takes the first as the user's, so the second is
simulated in the same batch, same legs, silently.

The check errors only where both fields are known from the files: a league round
books its whole division, a Swiss matchday all 36, a cup's opening round whoever
declares that entry round. Anything downstream of a draw is a superset, so it
warns instead and names how many of the overlapping clubs can actually reach
that round — a cup final shares a date with two clubs, not with everyone who
might have got there.

To repair what it finds:

```bash
php artisan app:fix-season-clashes 2026            # propose moves
php artisan app:fix-season-clashes 2026 --apply    # write them
```

It moves the certain clashes only, never touches a continental date (the UEFA
calendar is real and shared by every country's data), prefers moving a knockout
round over a league matchweek, keeps the round between its neighbours and on the
competition's usual weekday, and re-checks the whole season after each move so a
fix cannot create the next clash. Anything it cannot solve within a week either
side is reported for you to move by hand.

Deliberately not modelled, because a data gate cannot know them: a supercup from
season 2 on (its field is last season's champion and cup winner), promotion
playoffs, the Coppa Italia's byes once re-derived from a final table, and
pre-season friendlies (the opponent is the user's choice).

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
