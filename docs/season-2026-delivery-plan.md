# Season 2026 delivery plan

How to land `season-data/2026` (55 commits, 275 files, ~136k inserted lines) as a
series of independently mergeable, independently revertible changes instead of
one release, while production stays pinned to `GAME_SEASON=2025`.

## The two axes

There are two different questions in play, and they do not line up:

- **Verifiability.** Can a machine prove this correct? Tests, `app:validate-season`
  and PHPStan settle code and *structural* data (a bracket halves, a shirt is
  unique, a league has `2 × (teams − 1)` rounds). They cannot settle whether
  Levante were actually relegated, whether matchday 1 is really 23 August 2026,
  or whether Ipswich are a "modest" club. That second class is **unsafe**: the
  gate passes and the content is still wrong.
- **Blast radius on a 2025-pinned production.** Does merging this change what a
  live save does today?

Most of the branch is safe on both axes. A little is safe to verify but still
changes 2025 behaviour. And the unverifiable content — the 2026 squads,
calendars and cup fields — is almost entirely *inert* while the season pin says
2025. Splitting on blast radius, not on file type, is what shrinks the risky
merge from 275 files to two.

## What was checked

Evidence behind the split, all reproducible from the branch tip:

1. **`config/countries.php` is the master switch, and it is hard-coupled to
   `data/2026`.** On the branch tip, `php artisan app:validate-season 2025`
   *fails* with 15 errors — `POR1`, `NED1`, `ENGCUP`, `ENGLC`, `ENGSUP`,
   `DEUCUP`, `DEUSUP`, `ITACUP`, `ITASUP`, `FRACUP`, `FRASUP`, `PORCUP`,
   `PORSUP`, `NEDCUP`, `NEDSUP` all have no `data/2025/` folder. The moment that
   config lands, season 2025 is an invalid configuration: `app:seed-reference-data`
   cannot rebuild it and CI cannot validate it.
2. **`data/2026` does not validate against main's config either.** With the
   branch's data but main's `config/countries.php`, `app:validate-season 2026`
   fails on 10 clubs: the EUR pool shrank from 71 to 61 because Benfica, Porto,
   Sporting, Braga, Ajax, PSV, Feyenoord, Twente, AZ and NEC moved into the new
   `POR1`/`NED1` `teams.json`, so the UCL/UEL/UECL participant lists lose their
   squad source. The config and the data have to arrive together — or the config
   has to learn which season it applies to (see [Season-gating](#the-one-addition-worth-making-season-gating)).
3. **The season-transition processors were already made backward-compatible on
   this branch.** `DomesticCupQualificationProcessor` and
   `SupercupQualificationProcessor` now return early when the competition has no
   `competitions` row, and when the *game* holds no entries for it. That is what
   lets a new cup be declared without breaking a save that started before it
   existed. It must merge before the config that declares them.
4. **`data/players/player_positions_ES.json` is purely additive** — 742 → 1458
   entries, 716 added, 0 removed, 0 changed. It cannot regress a 2025 player.
5. **`public/crests/` is 82 additions, no modifications.** The `TeamColors`
   changes to existing providers (`Bundesliga`, `Ligue1`, `PremierLeague`,
   `SerieA`, `PrimeraFederacionA`, `PrimeraFederacionB`) contain no deleted
   lines. The Portugal/Netherlands entries moved out of `EuropeanTransferPool`
   into new `PrimeiraLiga`/`Eredivisie` providers registered in
   `TeamColors::teams()` — nine clubs, of which **Sporting CP and Go Ahead
   Eagles come back with different colours**. That is the one visible change
   hiding in an otherwise net-neutral move.
6. **The UI degrades cleanly.** `SelectTeam` builds the country picker with
   `Competition::find()` and drops a country whose tiers resolve to nothing, and
   `JobOfferService::eligibleTeamIdsForTier` returns an empty collection for a
   league with no teams. So declaring PT/NL in config without seeding them is
   invisible at runtime — the breakage is confined to the seed/validate commands.
7. **The branch is one commit behind main**, and that commit
   (`4ecaf90`, #1340) touches `config/countries.php`. Rebase before splitting.

## The deliverables

D1–D5 can all be merged and deployed against a production pinned to 2025. They
are ~95% of the non-data code and none of the manual verification.

### D1 — Refresh tooling and scraper *(inert)*

`app/Console/Commands/ListMissingPlayerPositions.php`,
`app/Console/Commands/MergePlayerPositions.php`, `app/Support/PlayerPositionsData.php`,
`scripts/transfermarkt-scraper/*`, `tests/js/scraper-batch-positions.test.js`,
the `ScaffoldSeason` hint text, `.gitignore`, `docs/season-data-refresh.md`.

Nothing here is reachable from `app/Http` or a season pipeline. The scraper is a
browser extension that is not deployed at all. `season-config.js` names the new
competitions, which is harmless — it is a lookup table for a developer tool.

*Verification:* `npm test`, `php artisan test --filter=…` for the two commands.

### D2 — Validator hardening *(inert)*

`ValidateSeason` bracket-parity and duplicate-shirt checks, the `numbers`/
`positions` maps added to `SeasonData::readCompetitionClubs`,
`tests/Feature/Console/ValidateSeasonCommandTest.php`.

CI- and developer-only. The `SeasonData` change is additive — existing callers
read `id`/`name`/`players` and are untouched. Confirmed to pass against
`data/2025` as it stands.

*Verification:* `app:validate-season 2025` must still pass on this slice alone.

### D3 — Data-integrity fixes *(production-visible, small, independently valuable)*

Two unrelated bugs that happen to have been found during the refresh. Ship them
because they are fixes for 2025, not because 2026 needs them.

- **Blank shirt numbers must land as `NULL`, not `0`** —
  `GamePlayerTemplateService::prepareTemplateRow` plus
  `tests/Unit/GamePlayerTemplateSquadNumberTest.php`. The partial unique index on
  `(season, team_id, number)` covers every non-null value, so two shirtless
  team-mates currently collide and `insertOrIgnore` silently drops one.
- **Canonical club names** — `app/Support/ClubNames.php`, the
  `2026_09_03_000001_canonicalize_renamed_club_names` migration, the
  `ClubNames::canonical()` hooks in `SeedReferenceData` (plus the
  `SeasonData::resolveTransfermarktId` fix for cup clubs scraped with
  `transfermarktId` rather than `id`), the `Deportivo A Coruña` key in
  `ClubProfilesSeeder` and `TeamColors\LaLiga2`, and the matching comment in
  `config/commercial.php`.

  **This one is atomic.** The migration renames two `teams` rows by
  `transfermarkt_id` (897 Deportivo, 40812 Universitatea Craiova); the seeder,
  club-profile and kit-colour maps are keyed by *name*. Land them in separate
  commits and Deportivo loses its club profile and its kit in between.

*Blast radius:* two rows, keyed by a stable id, reversible via `down()`.
*Manual check:* confirm 897 and 40812 are the only rows on an old spelling in
production before deploying.

### D4 — Dormant additions *(merge whenever)*

Everything that is a lookup entry nothing reaches yet:

- `EflCupConfig`, `PrimeiraLigaConfig`, `EredivisieConfig` — unreferenced until
  `config/countries.php` names them.
- `Competition::SHORT_NAMES` / `ABBREVIATIONS` / article map for `POR1`, `NED1`,
  `ENGSUP`, `FRASUP`, `DEUSUP`.
- The four `lang/{es,en}/{cup,season}.php` additions (extra round names,
  Portugal/Netherlands award strings). Both locales, as required.
- `config/commercial.php` Portugal and Netherlands sponsor pools.
- `TeamColors`: the new `PrimeiraLiga`/`Eredivisie` providers, their registration,
  and the additive entries in the six existing providers.
- `ClubProfilesSeeder` rows for clubs new to the 2026 dataset.
- The 82 new `public/crests/*.png`.

*Blast radius:* none, except the Sporting CP and Go Ahead Eagles colour change
noted above, which is visible today in the transfer pool. `ClubProfilesSeeder`
rows only take effect on a reseed.

### D5 — Season-transition engine *(the only pre-flip behaviour change)*

- **Guards** (prerequisite for D6): the `Competition::whereKey(...)->exists()`
  and per-game `hasCupEntries` / `hasSupercupEntries` early returns in
  `DomesticCupQualificationProcessor` and `SupercupQualificationProcessor`.
  Strictly more conservative than what is on main; a no-op for an ES save, which
  has `ESPCUP` entries either way.
- **`entry_rounds` mechanism**: `CupEntryRoundService`'s `fieldDecidesRounds`
  branch and `DomesticCupQualificationProcessor::leagueEntryRounds`. Completely
  dormant — no cup declares `entry_rounds` until D6 adds `ITACUP` and `NEDCUP`.
- **Multiple cup-winner slots**: `CountryConfig::cupWinnerSlot()` →
  `cupWinnerSlots()` (single map → ordered list), and the
  `UefaQualificationProcessor` rework — `europeanRank()` comparison instead of
  hardcoded UCL/UEL/UECL cases, the ghost-winner guard, and the
  no-winner-cascade branch.
- Tests: `DomesticCupQualificationTest`, `SupercupQualificationTest`,
  `CupEntryRoundServiceTest`, `AwardCupPrizeMoneyTest`, `UefaQualificationTest`.

Reshape **only the ES block** of `config/countries.php` here — `cup_winner_slot`
becomes a one-element list with identical values, and `EN`/`DE`/`IT`/`FR` become
`[]` instead of `null`. Semantically unchanged. Everything else in that file
waits for D6.

*Blast radius — read this one.* Two branches fire on existing 2025 saves:

- **A ghost Copa del Rey winner no longer takes a European place.** `ESPCUP` has
  116 clubs and most are squadless ghosts, so this is reachable today. Previously
  the ghost got the UEL berth; now `hasSquad()` rejects it and the slot cascades
  down the table. A fix, but a change.
- **A country whose cup was never drawn now cascades its cup slot into the league
  table** instead of leaving it unallocated. In a German save, Spain's Copa berth
  now goes to 5th in ESP1 rather than being backfilled generically.

Both are defensible improvements. Neither is a no-op, so D5 wants its own deploy
window and its own line in the changelog.

### D6 — The flip *(the irreducible core)*

`config/countries.php` in full — Portugal and Netherlands tiers, all twelve new
`domestic_cups` blocks, `cup_qualification`, `supercup`, the re-allocated
`continental_slots`, the `cup_winner_slot` declarations — plus `data/2026/**`
and `data/players/player_positions_ES.json`.

This is where every manual verification lives, and it is the one merge that
invalidates season 2025 unless the season gate below goes in first.

Note that even here, the *risky* half is small: `data/2026/**` is inert at
runtime while the pin says 2025, because nothing reads `data/{season}` outside
the seeder. What actually changes production is ~600 lines of config, and within
that, three deliberate re-allocations:

| Country | main | branch |
|---|---|---|
| England | UCL 1–5, UEL 6, UECL 7 (7 places) | UCL 1–5, UEL 6, FA Cup → UEL, EFL Cup → UECL (**8 places**) |
| Germany | UCL 1–4, UEL 5–6, UECL 7 | UCL 1–4, UEL 5, UECL 6, Pokal → UEL |
| Italy | UCL 1–5, UEL 6, UECL 7 | UCL 1–4, UEL 5–6, UECL 7, Coppa → UEL |

These change which foreign clubs appear in the Swiss league phases of **existing
2025 saves**, because `UefaQualificationProcessor` runs `processCountry()` for
every country in `allCountryCodes()`, not just the user's.

## The one addition worth making: season-gating

Finding 1 above is the single largest risk in the whole release: once D6 merges,
`GAME_SEASON=2025` is a broken configuration. Production keeps running — live
saves are fine, the picker degrades cleanly — but reference data can no longer be
rebuilt, CI can no longer validate a 2025 data touch, and **there is no way back
except reverting the merge**.

Roughly 60 lines fixes that. Add an optional `from_season` key to a country block
and to individual `domestic_cups` entries, and thread the season through the two
places that enumerate them:

- `CountryConfig::playableCountryCodes(?string $season = null)` and
  `domesticCupIds(string $country, ?string $season = null)`, defaulting to
  `config('season.current')`, skipping anything whose `from_season` is later.
- `SeasonData::competitions(CountryConfig $c, string $season)` passes it through;
  `SeedReferenceData`, `RefreshPlayerTemplates` and `ValidateSeason` already know
  their season and pass it.
- The other five `playableCountryCodes()` callers (`SelectTeam`, `JobOfferService`,
  `UnstickSeasonTransition` ×2) keep the default. `UnstickSeasonTransition` should
  arguably pass `$game->base_season`; worth a follow-up, not a blocker.

What that buys:

- `app:validate-season 2025` and `app:seed-reference-data` keep working after D6.
- **The season flip becomes an environment change, not a merge.** `GAME_SEASON`
  is already read from env in `config/season.php`, so rolling 2026 back becomes
  editing one variable and redeploying — no revert, no rebuild.
- D6 itself becomes safe to merge on its own schedule, decoupled from the day you
  want players to see 2026.

Ship it as **D5.5**, between the engine work and the flip.

## Manual verification checklist for D6

Nothing below is provable by the test suite or `app:validate-season`. The
validator confirms the *shape*; a human has to confirm the *content*.

1. **Fixture calendars.** Every `data/2026/*/schedule.json` was generated by
   `79f7489 "Bootstrap the 2026 schedules from 2025, shifted a whole number of
   weeks"` and never revisited. ESP1 matchday 1 reads `2026-08-23`, which is
   `2025-08-17` plus 53 weeks — not a real 2026/27 calendar. Every league, cup
   round and continental matchday date needs checking against the published
   calendars.
2. **Promotion and relegation.** The 2026 club lists for ESP1, ESP2, ESP3A,
   ESP3B, ENG1, DEU1, FRA1, ITA1, POR1, NED1.
3. **Squads.** Transfers in and out, loans, market values, shirt numbers. The CI
   `app:diff-season` PR comment is the artefact to read here — it exists for
   exactly this.
4. **Cup fields.** ESPCUP 116, ENGCUP 64, ENGLC 32, DEUCUP 64, ITACUP 44,
   FRACUP 64, PORCUP 64, NEDCUP 58 clubs. The new parity check proves each
   bracket halves; it says nothing about whether the right clubs are in it, or
   whether the ghost/real mix is plausible.
5. **Entry-round rules.** The Coppa Italia's top-eight bye to round 4 and the
   KNVB Beker's top-six bye to round 2 are approximations of a real rule
   (Italy: last season's top eight; Netherlands: the clubs in Europe). Confirm
   the approximation is acceptable.
6. **Continental participant lists and seeding pots.** UCL/UEL/UECL at 36 each,
   plus the league-phase pots added by hand in `2d11c9e`.
7. **The three continental re-allocations** in the table above.
8. **92 unprofiled clubs.** `app:validate-season 2026 -v` lists them; they seed
   as local reputation with neutral loyalty. Decide which deserve a
   `ClubProfilesSeeder` entry.
9. **Hand-picked entries** — e.g. `2cd9d1d "Field Manchester City in the 2026
   Community Shield"`.
10. **716 new secondary-position entries**, and the kit colours, crests and
    naming-rights brands for clubs new to the dataset.

## As built

Seven branches, each stacked on the previous, so every pull request's diff is
only its own slice. Merge in order.

| Branch | Files | What it is |
|---|---|---|
| `claude/season-2026-d1-refresh-tooling` | 13 | D1 + the `SeasonData` shirt/position maps the new commands read |
| `claude/season-2026-d2-validator-hardening` | 2 | D2 |
| `claude/season-2026-d3-data-integrity` | 8 | D3, as two commits — shirt numbers, then club names |
| `claude/season-2026-d4-dormant-additions` | 103 | D4, as two commits — reference data, then the selector UI |
| `claude/season-2026-d5-transition-engine` | 10 | D5 |
| `claude/season-2026-d55-season-gate` | 10 | D5.5 |
| `claude/season-2026-d6-flip` | 139 | D6 |

Three things came out differently from the plan above:

- **`SeasonData::readCompetitionClubs` moved from D2 to D1.**
  `app:list-missing-positions` reads `$club['positions']`, so the shared
  primitive has to precede the tooling that consumes it.
- **The branch's tests had to be partitioned along the same seam as the code.**
  Six of them assert D6's config — England's two cups, a single-tier country's
  cup rebuild, the EFL Cup's prize table — and fail against D5's. They ship with
  D6. D5 keeps the mechanism tests and gains a ghost-winner test written against
  the Copa, which is where the guard actually earns its place: most of a
  116-club field has no squad. Two of D6's tests now set
  `season.current` to 2026 explicitly, because the gate hides England's cups
  from any earlier season — the test suite documenting the gate is the point.
- **The gate is data-layer only.** `playableCountryCodes()`, `domesticCupIds()`,
  `supercup()` and the new `transferPool()` take an optional season defaulting
  to `config('season.current')`; `SeedReferenceData`, `RefreshPlayerTemplates`,
  `ValidateSeason`, `NormalizeSeason`, `DiffSeason` and `ScaffoldSeason` pass
  theirs explicitly. Runtime callers were left alone: a save is never handed a
  competition added after it began, because D5's processors skip a cup with no
  `competitions` row and one the game holds no field for.

Verified on the full stack: `./vendor/bin/phpstan analyse` clean, 1304 tests
pass, `npm test` passes, and — the point of D5.5 — **`app:validate-season` passes
for both 2025 and 2026 with the whole thing merged**. Before it, season 2025
failed with 15 missing-folder errors.

One thing the gate does *not* cover, by design: `continental_slots` and
`cup_winner_slot` are read per-game at season transition and are not
season-versioned, so England's, Germany's and Italy's re-allocations reach
existing 2025 saves as soon as D6 merges. Versioning them would mean carrying
two shapes of every country's slot table in config for one season's benefit.
The cascade means no country loses a place before its cups exist — England
gains one, to the eight it really has — but which foreign clubs appear in the
Swiss draws does move.

## Sequencing and mechanics

```
main
 ├── D1  refresh tooling + scraper            inert
 ├── D2  validator hardening                  inert
 ├── D3  shirt-number NULL + club names       small, atomic, has a migration
 ├── D4  dormant additions                    inert (bar 2 kit colours)
 ├── D5  transition guards + UEFA cascade     behaviour change, own deploy window
 ├── D5.5 season-gate the registry            recommended, unlocks rollback
 └── D6  countries.php + data/2026            manual sign-off, then GAME_SEASON=2026
```

- D1–D4 are order-independent and can go in parallel.
- D5 must precede D6: its guards are what stop a newly declared cup from breaking
  a season transition in a save that started before it existed.
- D5.5 must precede D6 to keep 2025 seedable.
- **Build each PR by checking out paths, not commits.** The branch's history is
  refresh-shaped — 55 commits with CI-generated `Canonicalize season 2026 squad
  data` steps interleaved — so cherry-picking will fight you. Branch from main
  and `git checkout origin/season-data/2026 -- <paths>` per deliverable.
- Rebase `season-data/2026` onto main first: it is one commit behind, and that
  commit (#1340) touches `config/countries.php`.
- `.github/workflows/season-data.yml` only fires on `data/**`, so D1–D5 go
  through the normal test workflow. D6 triggers the season-data job, which will
  normalize, validate and post the transfer diff — that comment is the review
  artefact for checklist item 3.
- After D6 and the `GAME_SEASON` flip, re-run
  `docs/season-data-refresh.md`'s "Releasing to a database with live saves"
  verification: take an existing 2025 save through a cup draw and a full season
  transition, not just a squad page load.
