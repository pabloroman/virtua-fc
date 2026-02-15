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
],
```

**Changes:**
- Create `config/countries.php` with the declarative structure
- Create a `CountryConfig` service class that reads this config
- Refactor `PromotionRelegationFactory` to read from config instead of hardcoded `SpanishPromotionRule`
- Refactor `UefaQualificationProcessor` to read continental_slots from config
- Refactor `SelectTeam` view to group teams by country → tier
- Update `SeedReferenceData::$profiles` to be generated from country config

**What this unlocks:** Adding a new playable country becomes a config change + JSON data, not code changes.

**Risk:** Low. This is a pure refactor — extract implicit knowledge into explicit config. No schema changes, no behavior changes.

### Phase 2: Make `SeedReferenceData` country-driven

**Goal:** The seeder reads the country config and seeds all competitions for a country as a unit.

**Changes:**
- Restructure `data/` directory to be country-based: `data/2025/ES/ESP1/`, `data/2025/ES/ESP2/`, etc.
  (or keep flat but let the config point to paths)
- `SeedReferenceData` iterates countries, not individual competitions
- Per country: seed tiers (leagues + players), then cups (link existing teams), then continental (link existing teams)
- The seeder knows the dependency order: leagues before cups, domestic before continental

**What this unlocks:** `php artisan app:seed-reference-data --country=ES` seeds everything for Spain in the right order. Adding England means adding config + JSON, then `--country=GB`.

**Risk:** Low-medium. Changes the seeder structure but the individual seeding operations remain the same.

### Phase 3: Replace hardcoded competition IDs in processors

**Goal:** Processors use `CountryConfig` to resolve competition IDs dynamically.

**Changes:**
- `UefaQualificationProcessor`: reads `continental_slots` from `CountryConfig` for the game's country
- `PromotionRelegationProcessor`: reads `promotions` from `CountryConfig`
- `SupercopaQualificationProcessor`: reads domestic cup config from `CountryConfig`
- `Competition::CONFIG_MAP`: replace hardcoded map with a lookup through `CountryConfig` or move to config
- `GameProjector::onGameCreated`: resolve competition from team's country + tier, no 'ESP1' fallback

**What this unlocks:** All season-end processing works for any configured country without code changes.

**Risk:** Medium. These processors are critical to game integrity. Thorough testing required per processor.

### Phase 4: Add `competition_scope` and clean up `role`

**Goal:** Make the distinction between domestic/continental/reference explicit on the Competition model.

**Changes:**
- Add `scope` field to `competitions` table: 'domestic' | 'continental' | 'international'
- Deprecate the `foreign` value of `role` — foreign leagues are `scope=domestic, active=false` (they belong to another country's domestic setup, just not actively played)
- Add `active` boolean (or derive from: "is this competition's country the game's country, or is it continental?")
- Update queries that use `Competition::ROLE_FOREIGN` to use the new fields

**What this unlocks:** Cleaner queries. `Competition::where('scope', 'domestic')->where('country', $gameCountry)` instead of `whereIn('role', ['primary', 'domestic_cup'])`.

**Risk:** Medium. Schema migration + updating queries across the codebase.

### Phase 5: Connect `Game` to a country

**Goal:** The Game model knows which country the career is in, enabling all downstream lookups.

**Changes:**
- Add `country` field to `games` table (or derive from `team.country`)
- `SetupNewGame` uses game's country to determine which competitions to set up
- Game creation flow: user picks country → tier → team (instead of flat team list from all primary competitions)

**What this unlocks:** Multi-country support. The game knows "I'm a Spain career" and can set up the right ecosystem.

**Risk:** Low. Small schema change, mostly affects game creation flow.

### Phase 6: Tournament mode (World Cup)

**Goal:** Implement the World Cup as a tournament mode.

**Changes:**
- New `GroupStageKnockoutHandler` competition handler
- National team data (48 teams with squads)
- Tournament-specific `SetupNewGame` flow: no finances, no transfers, no season progression
- Group stage: 12 groups of 4, top 2 + best 3rd advance
- Knockout phase: R32 → R16 → QF → SF → Final
- Single-season game with no season-end pipeline

**What this unlocks:** The second game mode.

**Risk:** High. This is a new feature with a new handler, new data, and new game flow. But phases 1-5 make the architecture ready for it — tournament mode is just another "country" config (international) with a single competition.

---

## What stays the same

- **Event sourcing architecture** — untouched
- **CompetitionHandler interface** — extended, not changed
- **Match simulation** — untouched
- **Financial model** — untouched (career-only)
- **Player development** — untouched
- **Transfer system** — untouched (career-only)
- **Season end pipeline** — processors refactored to read config, but pipeline itself unchanged
- **JSON data format** — unchanged (same teams.json, schedule.json structure)

## Summary: Is the assumption correct?

Yes — introducing Country and Tier as first-class concepts will simplify seeding significantly. Today, the implicit relationships between competitions are spread across:
- Hardcoded IDs in ~6 processor/config files
- Hardcoded profiles in the seeder
- Implicit knowledge about which competitions belong together

Making these relationships explicit in a declarative config means:
1. **Seeding becomes country-driven** — seed Spain, seed England, done
2. **Season-end processing becomes generic** — promotion rules, continental slots, cup qualification all read from config
3. **Adding a new country** goes from "modify 6+ files with new hardcoded IDs" to "add a config block + JSON data"
4. **Tournament mode** fits naturally as another entry in the same system
