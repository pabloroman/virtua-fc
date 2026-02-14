# Plan: Standardize Competition Formats

## Problem Analysis

The `matchdays.json` and `rounds.json` files across playable competitions have inconsistent schemas, which forces the seeding logic (`SeedReferenceData`) and fixture generation code (`LeagueFixtureGenerator`, `SetupNewGame`, `FixtureGenerationProcessor`) to contain scattered format-aware parsing. Worse, hybrid competitions (league + playoffs) don't follow a single consistent data flow.

### 1. Date Format Mismatch

| Competition Type | Format | Example |
|-----------------|--------|---------|
| League (`ESP1`, `ESP2`, `TEST1`) | `DD/MM/YY` | `"17/08/25"` |
| Cup/Swiss knockout (`ESPCUP`, `ESPSUP`, `UCL`, `TESTCUP`) | `YYYY-MM-DD` | `"2025-10-29"` |

This forces `Carbon::createFromFormat('d/m/y', ...)` in league code and `Carbon::parse()` in cup code — 6 different places must know which format to expect.

### 2. Matchdays Schema Divergence

**League matchdays.json** — flat array, 2 fields:
```json
[{"round": 1, "date": "17/08/25"}]
```

**Cup matchdays.json** — flat array, 3 fields, duplicate `round` values for two-legged ties:
```json
[
    {"matchday": "Semifinal (Ida)", "date": "2026-02-10", "round": 6},
    {"matchday": "Semifinal (Vuelta)", "date": "2026-03-03", "round": 6}
]
```

The seeder uses fragile `str_contains($matchdayName, 'Vuelta')` to detect second legs.

### 3. Three Broken Data Flows for Knockout Dates

This is the **core problem** — knockout/playoff dates reach the handler through three different paths:

| Competition | Where dates are defined | Where dates are stored | Where dates are read at runtime |
|---|---|---|---|
| **ESPCUP/ESPSUP** | `matchdays.json` | `cup_round_templates` table | `CupDrawService` reads `cup_round_templates` |
| **ESP2** | Hard-coded in `ESP2PlayoffGenerator::getRoundConfig()` | **Nowhere** — `cup_round_templates` never populated | `LeagueWithPlayoffHandler` calls generator directly |
| **UCL** | `matchdays.json` AND hard-coded in `SwissKnockoutGenerator::getRoundConfig()` | `cup_round_templates` seeded from JSON | Handler uses `SwissKnockoutGenerator` hard-coded dates, **ignoring** the seeded templates |

Only **ESPCUP** follows the intended flow: JSON → DB → handler. The other two bypass `cup_round_templates` entirely.

The UCL dates even **conflict** between the two sources:
- `matchdays.json`: Playoff first leg = `2026-02-17`
- `SwissKnockoutGenerator`: Playoff first leg = `{year}-02-11`

### 4. Separate `rounds.json` Files Are Redundant

`rounds.json` only contains `round` + `type` (one_legged/two_legged). This metadata can be inferred structurally from whether a round entry has a `second_leg_date`. Currently 4 separate `rounds.json` files must be kept in sync with their corresponding `matchdays.json`.

### 5. Hybrid Competitions Have No Unified Schedule

ESP2's `matchdays.json` contains only 42 league rounds — playoff dates aren't in the file at all. UCL's `matchdays.json` contains only knockout dates — league phase dates are computed by `SwissDrawService`. No single file describes the full competition schedule.

### 6. Language Inconsistency in Round Names

TESTCUP uses English (`"Semi-Finals"`) while all production competitions use Spanish (`"Semifinal"`). The `str_contains('Vuelta')` detection would break for English round names.

---

## Proposed Solution

### Core Design: Object-Based `matchdays.json` with `league` and `knockout` Sections

Replace the flat array format with an **object** that has two optional sections:

```json
{
  "league": [ ... ],    // present when competition has a league phase
  "knockout": [ ... ]   // present when competition has a knockout phase
}
```

Each competition type uses the sections it needs:

| Competition | `league` | `knockout` | Handler |
|---|---|---|---|
| ESP1 | 38 matchdays | — | `league` |
| ESP2 | 42 matchdays | 2 playoff rounds | `league_with_playoff` |
| TEST1 | 6 matchdays | — | `league` |
| ESPCUP | — | 7 cup rounds | `knockout_cup` |
| ESPSUP | — | 2 cup rounds | `knockout_cup` |
| UCL | — | 5 knockout rounds | `swiss_format` |
| TESTCUP | — | 2 cup rounds | `knockout_cup` |

**Note on UCL league phase:** The Swiss league phase dates are generated algorithmically by `SwissDrawService` (pot-based draw, scheduling by augmenting paths). These don't come from a fixed calendar and don't belong in JSON. The `league` section is omitted — only the `knockout` section is present.

### Unified League Entry Schema

```json
{"round": 1, "date": "2025-08-17"}
```

- `round`: integer, matchday number
- `date`: ISO `YYYY-MM-DD` format (was `DD/MM/YY`)

### Unified Knockout Entry Schema

```json
// Single-leg round
{"round": 1, "name": "Primera ronda", "date": "2025-10-29"}

// Two-legged round
{"round": 6, "name": "Semifinal", "first_leg_date": "2026-02-10", "second_leg_date": "2026-03-03"}
```

- `round`: integer, knockout round number
- `name`: human-readable round name (always Spanish)
- `date` OR `first_leg_date` + `second_leg_date`: determines one_leg vs two_leg structurally

**This eliminates:**
- The separate `rounds.json` file (type inferred from date fields)
- The `str_contains('Vuelta')` hack (no duplicate entries per round)
- The `matchday` vs `name` inconsistency

### Single Data Flow for All Knockout Dates

After standardization, ALL competitions follow the same flow:

```
matchdays.json["knockout"]  →  seeder  →  cup_round_templates  →  handler reads at runtime
```

This means:
1. `ESP2PlayoffGenerator::getRoundConfig()` reads from `CupRoundTemplate` instead of hard-coding dates
2. `SwissKnockoutGenerator::getRoundConfig()` reads from `CupRoundTemplate` instead of hard-coding dates
3. The seeder populates `cup_round_templates` for ALL competitions that have knockout rounds (including ESP2, which currently skips this)

---

## Concrete File Changes

### Data File: ESP1 (Pure League)

**Before** — flat array, `DD/MM/YY`:
```json
[
  {"round": 1, "date": "17/08/25"},
  {"round": 2, "date": "24/08/25"}
]
```

**After** — object with `league` section, ISO dates:
```json
{
  "league": [
    {"round": 1, "date": "2025-08-17"},
    {"round": 2, "date": "2025-08-24"}
  ]
}
```

Same changes for **ESP2** (42 rounds) and **TEST1** (6 rounds).

### Data File: ESP2 (League + Playoff)

**Before** — flat array, league only, no playoff data:
```json
[
  {"round": 1, "date": "17/08/25"},
  ...
  {"round": 42, "date": "31/05/26"}
]
```

**After** — object with both sections:
```json
{
  "league": [
    {"round": 1, "date": "2025-08-17"},
    ...
    {"round": 42, "date": "2026-05-31"}
  ],
  "knockout": [
    {"round": 1, "name": "Playoff Semifinal", "first_leg_date": "2026-06-07", "second_leg_date": "2026-06-14"},
    {"round": 2, "name": "Playoff Final", "first_leg_date": "2026-06-21", "second_leg_date": "2026-06-28"}
  ]
}
```

Playoff dates are pulled from what's currently hard-coded in `ESP2PlayoffGenerator::getRoundConfig()`, using the first Sunday of June pattern for the 2025 season.

### Data File: ESPCUP (Pure Cup)

**Before** — flat array, duplicate round entries for two-legged ties:
```json
[
    {"matchday": "Primera ronda", "date": "2025-10-29", "round": 1},
    ...
    {"matchday": "Semifinal (Ida)", "date": "2026-02-10", "round": 6},
    {"matchday": "Semifinal (Vuelta)", "date": "2026-03-03", "round": 6},
    {"matchday": "Final", "date": "2026-04-25", "round": 7}
]
```

**After** — object with `knockout` section, one entry per round:
```json
{
  "knockout": [
    {"round": 1, "name": "Primera ronda", "date": "2025-10-29"},
    {"round": 2, "name": "Segunda ronda", "date": "2025-12-03"},
    {"round": 3, "name": "Dieciseisavos de final", "date": "2025-12-17"},
    {"round": 4, "name": "Octavos de final", "date": "2026-01-14"},
    {"round": 5, "name": "Cuartos de final", "date": "2026-02-04"},
    {"round": 6, "name": "Semifinal", "first_leg_date": "2026-02-10", "second_leg_date": "2026-03-03"},
    {"round": 7, "name": "Final", "date": "2026-04-25"}
  ]
}
```

Same pattern for **ESPSUP**, **UCL**, **TESTCUP** (with Spanish names for TESTCUP).

### Data File: UCL (Swiss + Knockout)

**Before** — flat array, knockout dates only:
```json
[
    {"matchday": "Playoff de eliminación (Ida)", "date": "2026-02-17", "round": 1},
    {"matchday": "Playoff de eliminación (Vuelta)", "date": "2026-02-24", "round": 1},
    ...
    {"matchday": "Final", "date": "2026-05-30", "round": 5}
]
```

**After** — object, knockout only (league phase generated algorithmically):
```json
{
  "knockout": [
    {"round": 1, "name": "Playoff de eliminación", "first_leg_date": "2026-02-17", "second_leg_date": "2026-02-24"},
    {"round": 2, "name": "Octavos de final", "first_leg_date": "2026-03-10", "second_leg_date": "2026-03-17"},
    {"round": 3, "name": "Cuartos de final", "first_leg_date": "2026-04-07", "second_leg_date": "2026-04-14"},
    {"round": 4, "name": "Semifinal", "first_leg_date": "2026-04-28", "second_leg_date": "2026-05-06"},
    {"round": 5, "name": "Final", "date": "2026-05-30"}
  ]
}
```

### Files to Delete

| File | Reason |
|------|--------|
| `data/2025/ESPCUP/rounds.json` | Type inferred from matchdays.json structure |
| `data/2025/ESPSUP/rounds.json` | Type inferred from matchdays.json structure |
| `data/2025/UCL/rounds.json` | Type inferred from matchdays.json structure |
| `data/2025/TESTCUP/rounds.json` | Type inferred from matchdays.json structure |

---

## PHP Code Changes

### 1. `SeedReferenceData` (major refactor)

**`seedCupRoundTemplates()`** — simplify signature and logic:
```php
// Before: seedCupRoundTemplates($code, $season, $roundsData, $matchdaysData)
// After:  seedCupRoundTemplates($code, $season, $knockoutRounds)
```

New logic:
- Receives the `knockout` array from JSON directly
- Each entry has `round`, `name`, and either `date` or `first_leg_date`+`second_leg_date`
- Determine `type`: has `second_leg_date` → `two_leg`, otherwise → `one_leg`
- No more "Vuelta" string detection, no cross-referencing with `rounds.json`

**`seedLeagueCompetition()`** — read `matchdays.json` as object, extract `league` key.

**`seedCupCompetition()`** — stop loading `rounds.json`, read `knockout` section from matchdays object.

**`seedSwissFormatCompetition()`** — stop loading `rounds.json`, read `knockout` section from matchdays object.

**New: `seedLeagueWithPlayoffCompetition()`** — handles ESP2-type competitions:
1. Read `matchdays.json` as object
2. Seed league fixtures from `league` section (same as pure league)
3. Seed `cup_round_templates` from `knockout` section (same as pure cup)

Currently, ESP2 goes through `seedLeagueCompetition()` which ignores playoff data. The new method handles both phases.

### 2. `LeagueFixtureGenerator`

**`loadMatchdays()`** — parse JSON as object, return `["league"]` array:
```php
$data = json_decode(file_get_contents($path), true);
return $data['league'] ?? $data; // fallback for backward compat during migration
```

**`adjustMatchdayYears()`** — switch to ISO dates:
```php
// Before: Carbon::createFromFormat('d/m/y', $md['date'])
// After:  Carbon::parse($md['date'])
// Output: $date->format('Y-m-d') instead of $date->format('d/m/y')
```

### 3. `SetupNewGame`

**`generateLeagueFixtures()`** — ISO date parsing:
```php
// Before: Carbon::createFromFormat('d/m/y', $fixture['date'])
// After:  Carbon::parse($fixture['date'])
```

**`generateSwissFixtures()`** — same change (SwissDrawService will output ISO too).

### 4. `FixtureGenerationProcessor`

Same `Carbon::createFromFormat` → `Carbon::parse` change.

### 5. `SwissDrawService`

**`formatSchedule()`** — output ISO dates:
```php
// Before: $date->format('d/m/y')
// After:  $date->format('Y-m-d')
```

### 6. `ESP2PlayoffGenerator` — Read from `CupRoundTemplate`

**`getRoundConfig()`** — replace hard-coded dates with DB lookup:
```php
public function getRoundConfig(int $round, int $seasonYear): PlayoffRoundConfig
{
    $template = CupRoundTemplate::where('competition_id', 'ESP2')
        ->where('round_number', $round)
        ->firstOrFail();

    return new PlayoffRoundConfig(
        round: $round,
        name: $template->round_name,
        twoLegged: $template->isTwoLegged(),
        firstLegDate: $template->first_leg_date,
        secondLegDate: $template->second_leg_date,
    );
}
```

Since `cup_round_templates` are seeded from the JSON, this closes the loop: JSON → DB → handler.

### 7. `SwissKnockoutGenerator` — Read from `CupRoundTemplate`

Same approach as ESP2PlayoffGenerator — replace hard-coded dates in `getRoundConfig()` with a `CupRoundTemplate` lookup. The seeder already populates these from `UCL/matchdays.json`.

### 8. `SwissFormatHandler`

Currently calls `$this->knockoutGenerator->getRoundConfig($round, $seasonYear)`. After the `SwissKnockoutGenerator` change reads from `CupRoundTemplate`, the handler may need to pass `competitionId` instead of (or in addition to) `seasonYear` so the generator can query the right templates.

Update `generateKnockoutRound()` to pass `$competitionId` to the generator.

---

## Execution Order

1. **Convert all `matchdays.json` to object format** with `league`/`knockout` sections, ISO dates
2. **Delete all `rounds.json` files**
3. **Add ESP2 playoff round data** to `ESP2/matchdays.json` knockout section
4. **Update `SeedReferenceData`** — unified parsing, new `seedLeagueWithPlayoffCompetition()` method
5. **Update `LeagueFixtureGenerator`** — object-aware loading, ISO dates
6. **Update `SwissDrawService`** — ISO date output
7. **Update `SetupNewGame`** — ISO date parsing
8. **Update `FixtureGenerationProcessor`** — ISO date parsing
9. **Update `ESP2PlayoffGenerator`** — read from `CupRoundTemplate`
10. **Update `SwissKnockoutGenerator`** — read from `CupRoundTemplate`
11. **Update `SwissFormatHandler`** — pass `competitionId` to generator
12. **Fix TESTCUP round names** to Spanish
13. **Run tests** — verify correctness
14. **Run `php artisan app:seed-reference-data --fresh`** — verify end-to-end seeding

---

## Risk Assessment

- **Low risk**: Date format conversion is mechanical (find `d/m/y`, replace with ISO)
- **Low risk**: Object format for matchdays.json is additive — the `loadMatchdays()` fallback handles the transition
- **Medium risk**: ESP2 playoff templates are new — must ensure the `SeasonEndPipeline` processors that regenerate fixtures for new seasons also regenerate `cup_round_templates` with year-adjusted dates
- **Medium risk**: SwissKnockoutGenerator switching to DB reads — must ensure `cup_round_templates` are populated before knockout rounds are generated (they are, via seeder at game creation)
- **Mitigation**: Existing test suite covers match simulation, fixture generation, cup draws, and Swiss draws
