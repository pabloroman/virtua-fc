# Entity Hierarchy Refactor Plan

## Desired Model

```
GameMode
├── Career
│   └── Country (e.g., Spain)
│       └── FootballTier (e.g., Tier 1 = La Liga, Tier 2 = Segunda)
│           └── Team
│               └── eligible for → Competitions (domestic + continental)
│
└── Tournament
    └── World Cup (48 countries, group + knockout)
        └── NationalTeam (user picks one, single season)
```

Key concepts:
- A **Country** groups playable tiers and their domestic competitions
- A **FootballTier** determines which competitions a team is eligible for
- **Competition scope**: domestic (same country) vs continental (cross-country)
- **Competition format**: league, knockout, league_with_playoff, swiss_format, group_stage_knockout (World Cup)
- **Support teams**: non-playable teams that must exist for competitions and transfers to function

---

## Current Architecture Summary

### What exists today

| Concept | Current implementation |
|---|---|
| Game mode | `Game.game_mode` ('career' / 'tournament') — exists but tournament mode is unused |
| Country | `Competition.country` (2-char code) + `Team.country` — flat, no Country entity |
| Tier | `Competition.tier` (integer) — lives on competition, not a first-class concept |
| Competition role | `Competition.role` ('primary', 'domestic_cup', 'european', 'foreign') — ad-hoc classification |
| Competition format | `Competition.handler_type` ('league', 'knockout_cup', 'league_with_playoff', 'swiss_format') |
| Team → Competition | `competition_teams` pivot (many-to-many with season) |
| Eligibility | Hardcoded in processors: `UefaQualificationProcessor` has `ESP1` positions → UCL/UEL; `SpanishPromotionRule` has `ESP1` ↔ `ESP2` |
| Seeding | `SeedReferenceData` has a flat `$profiles` array with hardcoded paths, tiers, handlers per competition |
| Game setup | `GameProjector::onGameCreated` finds competition via `CompetitionTeam` lookup; `SetupNewGame` copies all competition_teams for all seasons |

### How seeding works today

1. `SeedReferenceData` iterates a flat config array per profile (production/test)
2. Each entry specifies: code, path, tier, handler, country, role
3. League competitions seed teams + players from JSON
4. Cup/Swiss competitions link to already-seeded teams
5. `ClubProfilesSeeder` assigns reputation levels via hardcoded team name mapping
6. No concept of "which country's ecosystem am I seeding" — it's all one flat list

### How game creation works today

1. User picks a team from `Competition::where('role', 'primary')` (shows ESP1, ESP2)
2. `InitGame` sends `CreateGame` command with teamId
3. `GameProjector::onGameCreated` finds the team's primary competition via `CompetitionTeam`
4. `SetupNewGame` copies **all** competition_teams for the season (not just the country's)
5. Initializes game players for all `ROLE_PRIMARY` and `ROLE_FOREIGN` competitions

---

## Support Teams: Current State and Desired Model

Competitions and transfers require teams beyond the ones the user can pick. These "support teams" aren't playable but must exist with full squads so that playable teams can compete against them and acquire their players.

### Three categories exist today (implicitly)

#### 1. Transfer pool teams (foreign leagues + EUR pool)

**What they are:** Teams from non-Spanish leagues (ENG1, DEU1, FRA1, ITA1) and the EUR team pool (~40 additional European clubs, including Dutch and Portuguese teams). They provide the player market for transfers, scouting, and loans.

**How they're currently handled:**
- Seeded as `role=foreign` competitions with full rosters in `SeedReferenceData`
- **Always** initialized as GamePlayers at game setup — `SetupNewGame::initializeGamePlayers()` loads all `ROLE_PRIMARY` + `ROLE_FOREIGN` competitions
- Used by `TransferService::getEligibleBuyers()`, `ScoutingService::generateResults()`, and `LoanService::findBestDestination()` — all query `whereIn('role', [ROLE_PRIMARY, ROLE_FOREIGN])`
- EUR pool is `handler_type=team_pool` with individual JSON files per team (named by Transfermarkt ID)
- Scouting tier gates international access: tiers 1-2 domestic only, tiers 3-4 can search internationally

**Key property:** These teams are always loaded regardless of game context. Every career game creates ~2,500+ GamePlayer rows for foreign league rosters the user will never play against — only interact with via transfers.

#### 2. Continental opponents (UCL/UEL/UECL non-Spanish teams)

**What they are:** The ~31 non-Spanish teams in the Champions League (and similar for UEL/UECL) that the user may face in European competition.

**How they're currently handled:**
- Referenced in `data/2025/UCL/teams.json` by Transfermarkt ID + pot + country
- Player data comes from two sources:
  - Foreign league teams (Bayern, PSG, etc.) — already have GamePlayers from transfer pool initialization
  - EUR pool teams (Galatasaray, etc.) — get GamePlayers loaded from individual `data/2025/EUR/{id}.json` files
- **Conditionally** initialized: only if the user's team qualifies for the competition (`SetupNewGame::initializeSwissFormatCompetitions` checks `CompetitionEntry` for user's team)
- Spanish UCL teams (Real Madrid, Barcelona, etc.) reuse their ESP1 GamePlayers — `initializeSwissFormatPlayersFromData` skips teams that already have players

**Key property:** There's a dependency chain: foreign leagues must be seeded first so that continental teams already have rosters. Teams not in any foreign league need EUR pool data. This ordering is implicit.

#### 3. Domestic cup opponents (lower-division teams)

**What they are:** Teams in Copa del Rey that aren't in ESP1 or ESP2 — lower-division and regional clubs. The `data/2025/ESPCUP/teams.json` has 120 teams, many of which only exist in the cup.

**How they're currently handled:**
- Seeded in `SeedReferenceData::seedCupTeams()` — creates Team records and `competition_teams` links with `entry_round`
- **No player seeding** for cup-only teams — only teams already in ESP1/ESP2 have rosters
- Cup matches against lower-division teams work because the match simulator can handle teams without full GamePlayer rosters (generates minimal match results)
- `entry_round` determines when teams enter: Supercopa teams enter at round 3, others at round 1

**Key property:** These teams have the weakest data — they exist in the `teams` table but may lack rosters. The cup can function because early rounds are simulated (not played by the user), and by the time the user's team enters, they face ESP1/ESP2 teams that have full rosters.

### What's missing: explicit support team categorization

Today, the distinction between these three categories is implicit:

| Category | How it's identified | Initialization trigger |
|---|---|---|
| Transfer pool | `Competition.role = 'foreign'` | Always (game setup) |
| Continental opponents | `Competition.handler_type = 'swiss_format'` + team not in foreign league | User qualifies for competition |
| Domestic cup opponents | `Competition.role = 'domestic_cup'` + team not in any league | Never (no GamePlayers created) |

**Problems with the current approach:**

1. **Transfer pool is over-sized**: Every game creates GamePlayers for 6 foreign leagues. NLD1 and POR1 can be moved to the EUR team pool (individual team files) to reduce the number of eagerly-loaded players — only teams that appear in European competitions or are relevant to transfers need full rosters.

2. **Continental opponents have fragile dependencies**: UCL initialization assumes foreign league teams already have GamePlayers, and uses EUR pool as a fallback. If a UCL team exists in neither, it silently gets skipped. This ordering dependency is implicit.

3. **Cup opponents lack rosters**: Lower-division cup teams have no players. This is acceptable — early cup rounds are auto-simulated and by the time the user's team enters, they face ESP1/ESP2 teams that have full rosters. No change needed here.

4. **No concept of "why" a team exists**: A team in the `teams` table could be there for any reason — playable, transfer pool, cup filler, continental opponent. There's no field or relationship that captures this intent.

### Desired model for support teams

Support teams should be an explicit part of the Country config:

```
Country (Spain)
├── Playable Tiers
│   ├── Tier 1: ESP1 (20 teams, full rosters) — user can pick these
│   └── Tier 2: ESP2 (22 teams, full rosters) — user can pick these
│
├── Domestic Support
│   ├── Cup pool: ESPCUP lower-division teams (team records only, no rosters)
│   └── Supercopa pool: ESPSUP teams (subset of ESP1)
│
├── Continental Support (shared across countries)
│   ├── UCL opponents: 36 teams (5 Spanish + 31 from other countries)
│   ├── UEL opponents: 36 teams
│   └── UECL opponents: 36 teams
│   (Non-Spanish teams need rosters; Spanish ones reuse league rosters)
│
└── Transfer Pool (shared across countries)
    ├── Foreign leagues: ENG1, DEU1, FRA1, ITA1 (full league rosters, eagerly loaded)
    └── EUR club pool: ~60 European clubs including NLD1/POR1 teams (individual team files)
    (All need full rosters for transfers/scouting/loans)
```

**Key design decisions for the refactor:**

1. **Eager initialization, smaller pool**: Transfer pool teams are always fully initialized at game setup (no lazy loading — simplicity over optimization). To keep the pool manageable, NLD1 and POR1 are moved from full-league foreign competitions to the EUR team pool (individual team files). Only their teams that appear in European competitions or are transfer-relevant need data.

2. **Roster requirements by category**: Each support team category declares what data it needs:
   - Transfer pool (foreign leagues): full roster from JSON (market values, contracts, abilities)
   - Transfer pool (EUR club pool): full roster from individual team files
   - Continental opponents: full roster (needed for match simulation) — reused from transfer pool where possible
   - Domestic cup opponents: team records only, no rosters needed (early rounds are auto-simulated; user faces league teams)

3. **Dependency chain should be explicit**: The country config should declare the initialization order — leagues first, then cups (link existing teams), then transfer pool, then continental (link + fill from transfer pool).

4. **Team provenance**: Each team's "reason for existing" should be traceable — is it playable, a cup filler, a continental opponent, or a transfer pool member? A team can have multiple roles (e.g., Bayern is both a transfer pool team and a UCL opponent).

---

## Delta Analysis

### 1. Missing: Country as a first-class entity

**Current:** Country is a 2-char string on `Competition` and `Team`. There's no entity that says "Spain has tier 1 (ESP1), tier 2 (ESP2), domestic cups (ESPCUP, ESPSUP), and feeds into continental (UCL, UEL, UECL)."

**Desired:** A `Country` (or `FootballCountry` / `PlayableCountry`) entity that groups:
- Its domestic tiers (ordered)
- Its domestic cups
- Its continental competition eligibility rules

**Impact:** This is the biggest missing piece. Today, the relationship "ESP1 and ESP2 are both Spanish, and ESPCUP involves teams from both" is implicit — scattered across hardcoded competition IDs in processors, seeders, and config.

### 2. Missing: Tier as an explicit concept linking teams to competition eligibility

**Current:** `Competition.tier` is an integer but it's just metadata. The actual eligibility is:
- Teams appear in a competition because they were seeded into `competition_teams`
- Promotion/relegation is hardcoded in `SpanishPromotionRule` (ESP1 ↔ ESP2)
- UEFA qualification is hardcoded in `UefaQualificationProcessor` (ESP1 positions → UCL/UEL)

**Desired:** A tier defines:
- Which league competition it maps to (1:1)
- What promotions/relegations connect it to adjacent tiers
- What continental spots it awards (by position)

**Impact:** Today, adding a new country would require: new promotion rule class, new UEFA qualification logic, new competition configs, new seeder entries, new JSON data. The tier concept would make this declarative.

### 3. Missing: Competition scope (domestic vs continental)

**Current:** `Competition.role` serves this purpose partially:
- `primary` = domestic league
- `domestic_cup` = domestic cup
- `european` = continental
- `foreign` = other countries' leagues (for scouting)

**Desired:** A cleaner separation:
- `scope`: 'domestic' | 'continental' | 'international'
- `type`: 'league' | 'cup' (the `handler_type` already handles format)

**Impact:** The `role` field conflates scope with purpose. `foreign` means "exists for scouting/transfers, not played." This is really about whether the competition is _active in the game_ vs _reference only_.

### 4. Hardcoded competition IDs throughout the codebase

**Current state of hardcoded IDs:**

| File | Hardcoded IDs |
|---|---|
| `Competition::CONFIG_MAP` | ESP1, ESP2, UCL, UEL, UECL |
| `UefaQualificationProcessor` | ESP1 → UCL, UEL |
| `SupercopaQualificationProcessor` | ESP1, ESPSUP (likely) |
| `SpanishPromotionRule` | ESP1, ESP2 |
| `SeedReferenceData::$profiles` | All competition codes |
| `GameProjector::onGameCreated` | Falls back to 'ESP1' |
| `SetupNewGame` | Queries by handler_type |

**Desired:** Competition IDs should be resolved through the Country → Tier → Competition chain, not hardcoded.

### 5. Seeding is procedural, not declarative

**Current:** `SeedReferenceData::$profiles` is a flat PHP array describing each competition independently. The seeder has separate methods per handler type (`seedLeagueCompetition`, `seedCupCompetition`, etc.).

**Desired:** A country-based manifest that declares: "For Spain, seed tier 1 from ESP1 data, tier 2 from ESP2 data, domestic cups are ESPCUP/ESPSUP, continental eligibility follows these rules."

### 6. Tournament mode is structurally absent

**Current:** `Game.game_mode` exists as 'career'/'tournament', and `isCareerMode()`/`isTournamentMode()` methods exist, but there's no World Cup competition handler, no national team concept, and no group-stage-then-knockout format.

**Desired:** Tournament mode with World Cup: 48 national teams, group stage (12 groups of 4), knockout phase. User picks a national team. Single season, no transfers/finances.

**Impact:** This is a completely new feature, not a refactor. It needs a new handler type ('group_stage_knockout'), national team data, and tournament-specific game setup.

### 7. Missing: Support team taxonomy and lifecycle

**Current:** Three categories of support teams exist (transfer pool, continental opponents, domestic cup opponents) but they're not modeled as such. The distinction is implicit in `Competition.role` and `handler_type` values, and each category has different initialization behavior buried in `SetupNewGame`.

**Desired:** An explicit declaration of support team categories per country, with:
- **What** teams are needed (by competition/pool)
- **Why** they exist (transfers, competition opponents, cup filler)
- **When** they get initialized (always, on qualification, on-demand)
- **What data** they need (full roster, generated squad, team record only)

**Impact:** Today, adding a new playable country requires understanding the implicit initialization order and roster requirements. Making this explicit in the country config turns it into a declarative setup.

---

## Incremental Transition Plan

### Phase 1: Introduce `FootballCountry` configuration (no schema changes)

**Goal:** Extract the implicit country → tiers → competitions → eligibility mapping into an explicit, declarative configuration.

Create `config/countries.php` (or `app/Game/Countries/`) that declares:

```php
'ES' => [
    'name' => 'Spain',
    'playable' => true,
    'tiers' => [
        1 => ['competition' => 'ESP1', 'teams' => 20],
        2 => ['competition' => 'ESP2', 'teams' => 22],
    ],
    'domestic_cups' => ['ESPCUP', 'ESPSUP'],
    'promotions' => [
        ['from' => 'ESP2', 'to' => 'ESP1', 'direct' => [1, 2], 'playoff' => [3, 4, 5, 6], 'relegated' => [18, 19, 20]],
    ],
    'continental_slots' => [
        'ESP1' => [
            'UCL' => [1, 2, 3, 4],
            'UEL' => [5, 6],
        ],
    ],
    // Support teams: what non-playable teams this country's ecosystem needs
    'support' => [
        'domestic_cup_pool' => [
            // Team records only — no rosters generated for lower-division teams.
            // Early cup rounds are auto-simulated; user faces league teams by the time they enter.
            'ESPCUP' => ['path' => 'data/2025/ESPCUP', 'roster' => 'none'],
        ],
        'transfer_pool' => [
            // Foreign leagues (full league rosters, eagerly loaded at game setup)
            'ENG1' => ['path' => 'data/2025/ENG1', 'roster' => 'reference'],
            'DEU1' => ['path' => 'data/2025/DEU1', 'roster' => 'reference'],
            'FRA1' => ['path' => 'data/2025/FRA1', 'roster' => 'reference'],
            'ITA1' => ['path' => 'data/2025/ITA1', 'roster' => 'reference'],
            // EUR club pool (individual team files — includes NLD1/POR1 teams)
            'EUR'  => ['path' => 'data/2025/EUR', 'roster' => 'reference', 'format' => 'pool'],
        ],
        'continental' => [
            // Teams needed for European competitions (rosters reused from transfer pool)
            'UCL' => ['path' => 'data/2025/UCL', 'roster' => 'from_pool'],
            // 'from_pool' = reuse GamePlayers from transfer pool; only create if missing
        ],
    ],
],
```

**Changes:**
- Create `config/countries.php` with the declarative structure including support teams
- Create a `CountryConfig` service class that reads this config
- Refactor `PromotionRelegationFactory` to read from config instead of hardcoded `SpanishPromotionRule`
- Refactor `UefaQualificationProcessor` to read continental_slots from config
- Refactor `SelectTeam` view to group teams by country → tier
- Update `SeedReferenceData::$profiles` to be generated from country config

**What this unlocks:** Adding a new playable country becomes a config change + JSON data, not code changes. Support team requirements are declared alongside the playable ecosystem.

**Risk:** Low. This is a pure refactor — extract implicit knowledge into explicit config. No schema changes, no behavior changes.

### Phase 2: Formalize support team lifecycle in `SetupNewGame`

**Goal:** Make the initialization of support teams explicit, ordered, and driven by the country config rather than scattered across `SetupNewGame` methods.

**Current problems:**
- Transfer pool includes NLD1 and POR1 as full foreign leagues, but these can be reduced to EUR pool entries
- Continental opponent initialization silently depends on transfer pool being loaded first
- `SetupNewGame` has separate code paths for each team category with no shared abstraction

**Changes:**

Refactor `SetupNewGame` to follow an explicit initialization pipeline driven by `CountryConfig.support`:

```
Step 1: Seed playable tiers (ESP1, ESP2) — teams + full rosters from JSON
Step 2: Seed domestic cup pool (ESPCUP) — link existing teams (team records only, no rosters)
Step 3: Seed transfer pool (ENG1, DEU1, FRA1, ITA1 + EUR pool) — teams + full rosters from JSON
Step 4: Seed continental opponents (UCL, UEL) — link teams, reuse rosters from steps 1+3, fill gaps from EUR
```

Specific improvements:
- **Smaller transfer pool**: Move NLD1 and POR1 from full foreign league competitions to the EUR team pool. Only their teams that appear in European competitions or are transfer-relevant get individual team files. This reduces the number of eagerly-loaded GamePlayers while keeping the transfer market diverse.
- **Explicit dependency tracking**: Each step in the pipeline knows what it depends on (e.g., continental opponents depend on transfer pool). The country config declares this order.
- **No cup roster generation**: Lower-division cup teams remain as team records only. Early cup rounds are auto-simulated and by the time the user enters, they face league teams with full rosters. This avoids adding complexity for an edge case.

**What this unlocks:** Game setup creates fewer GamePlayers (no NLD1/POR1 full leagues), the initialization order is documented and enforced, and the pipeline is driven by config rather than hardcoded method calls.

**Risk:** Low-medium. The main change is restructuring `SetupNewGame` into a config-driven pipeline. Moving NLD1/POR1 to EUR pool requires creating individual team JSON files for their relevant clubs, but the seeding mechanics already support this format.

### Phase 3: Make `SeedReferenceData` country-driven

**Goal:** The seeder reads the country config and seeds all competitions for a country as a unit, including its support teams.

**Changes:**
- Restructure `data/` directory to be country-based: `data/2025/ES/ESP1/`, `data/2025/ES/ESP2/`, etc.
  (or keep flat but let the config point to paths)
- `SeedReferenceData` iterates countries, not individual competitions
- Per country: seed tiers (leagues + players), then cups (link existing teams), then continental (link existing teams), then transfer pool
- The seeder knows the dependency order from the country config — leagues before cups, domestic before continental, continental before transfer pool
- Support teams are seeded as part of the country's ecosystem, not as standalone entries in a flat list

**What this unlocks:** `php artisan app:seed-reference-data --country=ES` seeds everything for Spain in the right order — playable tiers, cups, continental, and transfer pool. Adding England means adding config + JSON, then `--country=GB`.

**Risk:** Low-medium. Changes the seeder structure but the individual seeding operations remain the same.

### Phase 4: Replace hardcoded competition IDs in processors

**Goal:** Processors use `CountryConfig` to resolve competition IDs dynamically.

**Changes:**
- `UefaQualificationProcessor`: reads `continental_slots` from `CountryConfig` for the game's country
- `PromotionRelegationProcessor`: reads `promotions` from `CountryConfig`
- `SupercopaQualificationProcessor`: reads domestic cup config from `CountryConfig`
- `Competition::CONFIG_MAP`: replace hardcoded map with a lookup through `CountryConfig` or move to config
- `GameProjector::onGameCreated`: resolve competition from team's country + tier, no 'ESP1' fallback

**What this unlocks:** All season-end processing works for any configured country without code changes.

**Risk:** Medium. These processors are critical to game integrity. Thorough testing required per processor.

### Phase 5: Add `competition_scope` and clean up `role`

**Goal:** Make the distinction between domestic/continental/reference explicit on the Competition model.

**Changes:**
- Add `scope` field to `competitions` table: 'domestic' | 'continental' | 'international'
- Deprecate the `foreign` value of `role` — foreign leagues are `scope=domestic, active=false` (they belong to another country's domestic setup, just not actively played)
- Add `active` boolean (or derive from: "is this competition's country the game's country, or is it continental?")
- Update queries that use `Competition::ROLE_FOREIGN` to use the new fields

**What this unlocks:** Cleaner queries. `Competition::where('scope', 'domestic')->where('country', $gameCountry)` instead of `whereIn('role', ['primary', 'domestic_cup'])`.

**Risk:** Medium. Schema migration + updating queries across the codebase.

### Phase 6: Connect `Game` to a country

**Goal:** The Game model knows which country the career is in, enabling all downstream lookups.

**Changes:**
- Add `country` field to `games` table (or derive from `team.country`)
- `SetupNewGame` uses game's country to determine which competitions to set up, including all support teams declared in that country's config
- Game creation flow: user picks country → tier → team (instead of flat team list from all primary competitions)

**What this unlocks:** Multi-country support. The game knows "I'm a Spain career" and can set up the right ecosystem — playable leagues, cups, continental competitions, and transfer pool — all from one config lookup.

**Risk:** Low. Small schema change, mostly affects game creation flow.

### Phase 7: Tournament mode (World Cup)

**Goal:** Implement the World Cup as a tournament mode.

**Changes:**
- New `GroupStageKnockoutHandler` competition handler
- National team data (48 teams with squads)
- Tournament-specific `SetupNewGame` flow: no finances, no transfers, no season progression
- Group stage: 12 groups of 4, top 2 + best 3rd advance
- Knockout phase: R32 → R16 → QF → SF → Final
- Single-season game with no season-end pipeline
- Support teams: all 47 non-user teams are opponents — all need full rosters (national team squads from reference data)

**What this unlocks:** The second game mode.

**Risk:** High. This is a new feature with a new handler, new data, and new game flow. But phases 1-6 make the architecture ready for it — tournament mode is just another "country" config (international) with a single competition and 47 support teams.

---

## What stays the same

- **Event sourcing architecture** — untouched
- **CompetitionHandler interface** — extended, not changed
- **Match simulation** — untouched
- **Financial model** — untouched (career-only)
- **Player development** — untouched
- **Transfer system** — untouched (same eager loading, just fewer foreign league teams)
- **Season end pipeline** — processors refactored to read config, but pipeline itself unchanged
- **JSON data format** — unchanged (same teams.json, schedule.json structure)

## Summary

### Is the assumption correct?

Yes — introducing Country and Tier as first-class concepts will simplify seeding significantly. Today, the implicit relationships between competitions are spread across:
- Hardcoded IDs in ~6 processor/config files
- Hardcoded profiles in the seeder
- Implicit knowledge about which competitions belong together

Making these relationships explicit in a declarative config means:
1. **Seeding becomes country-driven** — seed Spain, seed England, done
2. **Season-end processing becomes generic** — promotion rules, continental slots, cup qualification all read from config
3. **Adding a new country** goes from "modify 6+ files with new hardcoded IDs" to "add a config block + JSON data"
4. **Tournament mode** fits naturally as another entry in the same system

### Support teams are the hidden complexity

The biggest insight from this analysis: support teams are where most of the seeding and setup complexity lives. Playable teams are straightforward (load from JSON, create GamePlayers). But the three categories of support teams — transfer pool, continental opponents, and domestic cup opponents — each have different:
- **Data sources** (league JSON, EUR pool files, cup JSON)
- **Initialization timing** (always, on qualification)
- **Roster requirements** (full reference data, reused from pool, none)
- **Dependency chains** (continental needs transfer pool; cups need league teams first)

The approach keeps things simple: all transfer pool teams are eagerly loaded (no lazy loading), cup teams don't need generated rosters, and the pool is trimmed by moving NLD1/POR1 to EUR individual team files. Making these categories and their requirements explicit in the country config is arguably more impactful than the Country/Tier hierarchy itself — it's what turns "adding a new country" from a multi-day task into a configuration exercise.

### Phase summary

| Phase | What | Risk | Schema changes |
|---|---|---|---|
| **1** | Create `config/countries.php` with Country → Tiers → Competitions → Support Teams | Low | None |
| **2** | Formalize support team lifecycle in `SetupNewGame` (explicit pipeline, move NLD1/POR1 to EUR pool) | Low-Med | None |
| **3** | Make `SeedReferenceData` country-driven | Low-Med | None |
| **4** | Replace hardcoded competition IDs in processors with config lookups | Medium | None |
| **5** | Add `scope` field to competitions, clean up `role` | Medium | Migration |
| **6** | Add `country` to `games` table, rework game creation flow | Low | Migration |
| **7** | Tournament mode (World Cup handler, national teams, group+knockout) | High | New feature |
