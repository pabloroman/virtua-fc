# Pre-Season with Friendly Matches — Implementation Plan

## Overview

Add a pre-season phase (July 1 – first competitive fixture) to career mode. During pre-season, the user plays 3-4 friendly matches against foreign teams of similar reputation. Friendlies have realistic consequences (injuries, fitness) but no card suspensions carry over. The summer transfer window is open throughout, giving users time to scout, sign players, and test lineups before results matter.

## Design Decisions

- **Scope:** Career mode only (tournament mode unaffected)
- **Match impact:** Realistic — injuries and fitness drain apply, but card suspensions do NOT carry over to competitive matches
- **Opponents:** Foreign teams (non-Spanish) with similar reputation (±1 tier from `ClubProfile.reputation_level`)
- **Number of friendlies:** 4 matches, scheduled roughly every 10 days from mid-July to mid-August
- **Skip option:** Users can skip pre-season entirely, fast-forwarding to the first competitive match

## Implementation Steps

### Step 1: Database Migration

Create migration to add `pre_season` boolean column to `games` table (default `false`).

**File:** `database/migrations/xxxx_add_pre_season_to_games_table.php`

### Step 2: Game Model Changes

**File:** `app/Models/Game.php`

- Add `'pre_season'` to `$fillable` and `$casts` (as boolean)
- Add `isInPreSeason(): bool` method
- Add `endPreSeason(): void` method that sets `pre_season = false`

### Step 3: Friendly Competition & Handler

#### 3a: Seed friendly competition

Add a "Pretemporada" competition record during reference data seeding. Competition attributes:
- `code`: `'FR'` (country-agnostic code)
- `name`: `'Pretemporada'` / `'Pre-Season'` (localized)
- `handler_type`: `'friendly'`
- `country`: matches the game's country (or null/generic)
- `role`: `'friendly'` (new role — not league, not cup)

One friendly competition per supported country, or a single shared one with `country = null`.

**File:** `app/Console/Commands/SeedReferenceData.php` — add friendly competition seeding
**Alternative:** Migration with DB insert for the competition record

#### 3b: Create FriendlyHandler

**New file:** `app/Modules/Match/Handlers/FriendlyHandler.php`

Implements `CompetitionHandler`:
- `getType()`: returns `'friendly'`
- `getMatchBatch()`: returns all matches on the same date (simple, like league)
- `beforeMatches()`: no-op
- `afterMatches()`: no-op (no standings, no cup tie resolution, no prize money)
- `getRedirectRoute()`: standard match result redirect

This is the simplest possible handler — friendlies just play and record results, nothing else.

#### 3c: Register handler

**File:** `app/Providers/AppServiceProvider.php`

Add `$resolver->register($app->make(FriendlyHandler::class))` alongside existing handlers.

### Step 4: Pre-Season Fixture Generation

**New file:** `app/Modules/Season/Processors/PreSeasonFixtureProcessor.php`

Priority: **108** (after `ContinentalAndCupInitProcessor` at 106, so all competitive fixtures exist)

Logic:
1. Skip if not career mode
2. Get user's team country from `$game->country`
3. Get user's team reputation from `ClubProfile`
4. Query foreign teams (`Team.country != $game->country`) with reputation within ±1 tier
5. Randomly pick 4 opponents (no duplicates)
6. Create 4 `GameMatch` records for the friendly competition:
   - Match 1: July 12 (home)
   - Match 2: July 22 (away)
   - Match 3: Aug 2 (home)
   - Match 4: Aug 10 (away)
6. AI teams don't need friendlies — only the user's team gets them

**File:** `app/Modules/Season/Services/SeasonEndPipeline.php` — register the new processor

### Step 5: Season Setup — Set Pre-Season Start Date

**File:** `app/Modules/Season/Processors/ContinentalAndCupInitProcessor.php`

Modify `finalizeCurrentDate()`:
- For career mode: set `current_date` to July 1 of the season year, set `pre_season = true`
- For tournament mode: keep existing behavior (earliest fixture date)
- Store the earliest competitive fixture date so the pre-season processor can reference it (or just compute it when needed)

### Step 6: Matchday Orchestrator — Suspend Card Suspensions for Friendlies

**File:** `app/Modules/Match/Services/MatchdayOrchestrator.php`

In `processPostMatchActions()` or `MatchResultProcessor`:
- When processing match results for the friendly competition, skip creating `PlayerSuspension` records
- Injuries and fitness changes still apply normally

**File:** `app/Modules/Match/Services/MatchResultProcessor.php`
- Check if the match's competition `handler_type` is `'friendly'` — if so, skip suspension creation

### Step 7: Pre-Season End Detection

**File:** `app/Modules/Match/Services/MatchdayOrchestrator.php`

After processing a batch, check:
- If `$game->isInPreSeason()` and no more unplayed friendly matches remain → call `$game->endPreSeason()`
- This naturally transitions to competitive play on the next advance

### Step 8: Skip Pre-Season Action

**New file:** `app/Http/Actions/SkipPreSeason.php`

Logic:
1. Delete all unplayed friendly matches for this game
2. Set `current_date` to the earliest unplayed competitive match date
3. Set `pre_season = false`
4. Dispatch `ProcessCareerActions` with 4 ticks (simulate the skipped weeks of transfer activity)
5. Redirect to game dashboard

**File:** `routes/web.php` — add route `POST /game/{gameId}/skip-pre-season`

### Step 9: First Game Creation

**File:** `app/Modules/Season/Jobs/SetupNewGame.php`

Ensure the same pre-season logic applies for brand-new games (not just season transitions):
- Set `current_date` to July 1, `pre_season = true` (for career mode)
- The `PreSeasonFixtureProcessor` will generate friendlies during game setup too (verify it runs in both paths)

### Step 10: UI Changes

#### 10a: Game Dashboard

**File:** `resources/views/game.blade.php`

When `$game->isInPreSeason()`:
- Show "Pre-Season" badge/header instead of matchday number
- Show countdown: "Season starts in X weeks" with the first competitive match date
- Show "Skip Pre-Season" button/link
- The next match card still works normally (shows the next friendly)
- Transfer window status prominently displayed ("Summer window open — closes Aug 31")

#### 10b: Game Header

**File:** `resources/views/components/game-header.blade.php`

- Show "Pretemporada" or "Pre-Season" label instead of "Jornada N" during pre-season
- "Continue" button works as normal (advances to next friendly)

#### 10c: Match Result / Live Match

**File:** `resources/views/match-result.blade.php` (or equivalent)

- Indicate "Friendly" on match result screen for friendly matches
- No league position changes shown

#### 10d: ShowGame View

**File:** `app/Http/Views/ShowGame.php`

- Pass `$isPreSeason`, `$seasonStartDate` (first competitive match date) to the view
- Pass `$weeksUntilSeason` for the countdown

### Step 11: Translations

**Files:** `lang/es/game.php`, `lang/en/game.php`

Add keys:
- `game.pre_season` — "Pretemporada" / "Pre-Season"
- `game.pre_season_friendly` — "Amistoso" / "Friendly"
- `game.skip_pre_season` — "Saltar pretemporada" / "Skip pre-season"
- `game.season_starts_in` — "La temporada empieza en :weeks semanas" / "Season starts in :weeks weeks"
- `game.season_starts_on` — "Inicio de liga: :date" / "League starts: :date"

### Step 12: Season End — Reset Pre-Season for Next Season

**File:** `app/Modules/Season/Processors/OnboardingResetProcessor.php`

Add `'pre_season' => true` to the update (for career mode) so the next season also starts with pre-season.

### Step 13: Tests

**New file:** `tests/Feature/PreSeasonTest.php`

Test cases:
- Pre-season flag is set on game creation (career mode)
- Pre-season flag is NOT set for tournament mode
- Friendly matches are generated with correct dates and foreign opponents
- Friendly matches don't create card suspensions
- Friendly matches do create injuries
- Pre-season ends when all friendlies are played
- Skip pre-season deletes friendlies and advances date
- Season transition resets pre-season for next season

## Files Changed Summary

| Type | File | Change |
|------|------|--------|
| New | Migration: `add_pre_season_to_games_table` | Add `pre_season` boolean column |
| Modified | `app/Models/Game.php` | Add field, methods |
| New | `app/Modules/Match/Handlers/FriendlyHandler.php` | New competition handler |
| Modified | `app/Providers/AppServiceProvider.php` | Register FriendlyHandler |
| New | `app/Modules/Season/Processors/PreSeasonFixtureProcessor.php` | Generate friendly fixtures |
| Modified | `app/Modules/Season/Services/SeasonEndPipeline.php` | Register new processor |
| Modified | `app/Modules/Season/Processors/ContinentalAndCupInitProcessor.php` | Set July 1 date, pre_season flag |
| Modified | `app/Modules/Match/Services/MatchResultProcessor.php` | Skip suspensions for friendlies |
| Modified | `app/Modules/Match/Services/MatchdayOrchestrator.php` | End pre-season detection |
| New | `app/Http/Actions/SkipPreSeason.php` | Skip pre-season action |
| Modified | `routes/web.php` | New route |
| Modified | `app/Http/Views/ShowGame.php` | Pass pre-season data to view |
| Modified | `resources/views/game.blade.php` | Pre-season dashboard UI |
| Modified | `resources/views/components/game-header.blade.php` | Pre-season label |
| Modified | `app/Modules/Season/Processors/OnboardingResetProcessor.php` | Set pre_season on transition |
| Modified | `app/Modules/Season/Jobs/SetupNewGame.php` | Ensure pre-season for new games |
| Modified | `lang/es/game.php` | Spanish translations |
| Modified | `lang/en/game.php` | English translations |
| New | `tests/Feature/PreSeasonTest.php` | Test coverage |

## Complexity: Medium

~3 new files, ~12 modified files, 1 migration. Estimated ~400-500 lines of new/changed code. The design reuses existing infrastructure heavily (matchday system, career action processor, competition handler pattern).
