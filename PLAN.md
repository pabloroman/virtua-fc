# World Cup Arcade Mode - Implementation Plan

## Overview

Add a parallel "World Cup Arcade" game mode to VirtuaFC that reuses the core match simulation engine and lineup management but strips away the career management layer (transfers, finances, contracts, scouting, youth academy). The player picks a national team and plays through a FIFA World Cup tournament (group stage → knockout rounds) in a fast, self-contained experience.

---

## Phase 1: Data Foundation

### 1.1 National Team Reference Data

Create `data/2025/WC/` directory with:

- **`teams.json`** — 48 national teams (2026 World Cup format) with:
  - Team name, country code, FIFA ranking-derived reputation
  - Crest image URLs (can use Transfermarkt national team images or placeholders)
  - Embedded player rosters (23-26 players per team) built from the existing `players` table by nationality
  - Each player inherits `technical_ability` / `physical_ability` from their club data

- **`groups.json`** — 12 groups of 4 teams (48-team format) with seeding based on reputation. Structure:
  ```json
  {
    "groups": [
      { "name": "A", "teams": ["ARG", "SAU", "MEX", "POL"] },
      ...
    ]
  }
  ```

- **`matchdays.json`** — Tournament calendar with dates for:
  - Group stage: 3 matchdays per group (matchdays 1-3)
  - Round of 32 (top 2 per group + 8 best 3rd-place teams)
  - Round of 16, Quarter-finals, Semi-finals, 3rd-place playoff, Final

- **`rounds.json`** — Knockout round templates (all single-leg):
  ```json
  [
    { "round": 1, "type": "single_leg", "name": "Round of 32" },
    { "round": 2, "type": "single_leg", "name": "Round of 16" },
    { "round": 3, "type": "single_leg", "name": "Quarter-finals" },
    { "round": 4, "type": "single_leg", "name": "Semi-finals" },
    { "round": 5, "type": "single_leg", "name": "Final" }
  ]
  ```

**Alternative: 32-team format** (simpler, classic) — 8 groups of 4, top 2 advance to Round of 16. This is simpler to implement and might be the better starting point. The plan uses 32 teams below but the architecture supports either.

### 1.2 Player-to-National-Team Mapping

Create a script/command `app:build-world-cup-data` that:
1. Reads all existing players from the `players` table (already seeded from club data)
2. Groups them by nationality (stored in `players.nationality` JSON column)
3. For each World Cup nation, selects the best 23-26 players by ability rating
4. Writes the assembled `data/2025/WC/teams.json` with embedded player rosters
5. Generates randomized but seeded group draws for `groups.json`

This leverages the ~3,000+ players already in the database from La Liga, Premier League, Bundesliga, Serie A, Ligue 1, Eredivisie, Primeira Liga, and European competition pools.

### 1.3 Seeder Profile

Add a `'world_cup'` profile to `SeedReferenceData`:

```php
'world_cup' => [
    [
        'code' => 'WC',
        'path' => 'data/2025/WC',
        'tier' => 0,
        'handler' => 'world_cup',
        'country' => 'INT',
        'role' => 'primary',   // selectable for team picker
    ],
],
```

Run: `php artisan app:seed-reference-data --profile=world_cup`

---

## Phase 2: Game Mode Infrastructure

### 2.1 Database: Add `game_mode` Column

New migration:

```php
Schema::table('games', function (Blueprint $table) {
    $table->string('game_mode', 20)->default('career');
});
```

Values: `'career'` (existing behavior), `'arcade_world_cup'`

### 2.2 Game Model Changes

Add to `App\Models\Game`:

```php
const MODE_CAREER = 'career';
const MODE_ARCADE_WORLD_CUP = 'arcade_world_cup';

public function isArcadeMode(): bool
{
    return $this->game_mode === self::MODE_ARCADE_WORLD_CUP;
}

public function isCareerMode(): bool
{
    return $this->game_mode === self::MODE_CAREER;
}
```

### 2.3 CreateGame Command Extension

Extend `CreateGame` command/event to carry `game_mode`:

```php
class CreateGame {
    public function __construct(
        public readonly string $userId,
        public readonly string $playerName,
        public readonly string $teamId,
        public readonly string $gameMode = 'career',  // new
    ) {}
}
```

The `GameCreated` event and `GameProjector::onGameCreated` branch on mode to control initialization.

---

## Phase 3: World Cup Competition Handler

### 3.1 New Handler: `WorldCupHandler`

Create `App\Game\Handlers\WorldCupHandler` implementing `CompetitionHandler`.

This handler manages **two phases**:

**Group Phase** (behaves like a mini-league):
- `getMatchBatch()` returns all group matches for the same matchday date
- `afterMatches()` checks if all group matches are complete → triggers knockout bracket generation
- Standings are tracked per-group using the existing `GameStanding` model (with a `group` column or by using sub-competition IDs like `WC-A`, `WC-B`, etc.)

**Knockout Phase** (reuses cup mechanics):
- Once groups complete, the handler generates `CupTie` records for R32/R16/QF/SF/F
- Delegates to `CupDrawService` for bracket creation (deterministic, not random — based on group positions)
- Single-leg matches with the existing `CupTieResolver` (already supports penalty shootout resolution)

**Key design decision:** Rather than creating a completely new handler, extend the pattern by composing `LeagueHandler` (for group stage) and `KnockoutCupHandler` (for knockout stage) behavior within a single `WorldCupHandler`. This avoids duplicating match scheduling and tie resolution logic.

### 3.2 Group Stage Standings

Two approaches (choose one):

**Option A — Sub-competitions:** Create `WC-A` through `WC-H` as separate competition records with `handler_type = 'league'` and `parent_competition_id = 'WC'`. Group standings use the existing `GameStanding` table per sub-competition. Clean but adds 8+ competition records.

**Option B — Group column on standings:** Add a nullable `group` column to `game_standings`. The `WorldCupHandler` filters standings by group. Simpler, no extra competition records.

**Recommendation: Option B** — it's less invasive and the `WorldCupHandler` can manage group filtering internally. One `game_standings` query with `->where('group', 'A')` is simpler than managing 8 sub-competition IDs.

### 3.3 World Cup Competition Config

Create `App\Game\Competitions\WorldCupConfig` implementing `CompetitionConfig`:

- No TV revenue / financial projections (arcade mode)
- `getSeasonGoal()` returns `'champion'` for top-seeded, `'group_stage'` for underdogs
- `getStandingsZones()` returns qualification zones: top-2 advance (green), 3rd-place possible (yellow), eliminated (red)
- Tiebreakers: points → goal difference → goals scored → head-to-head (FIFA rules)

### 3.4 Knockout Bracket Generation

After all group matches complete, `WorldCupHandler::generateKnockoutBracket()`:

1. Reads group standings (positions 1-2 from each group, optionally best 3rd-place)
2. Creates `CupRoundTemplate` records for each knockout round
3. Creates `CupTie` and `GameMatch` records following the FIFA bracket structure:
   - 1A vs 2B, 1C vs 2D, etc. (predetermined crossings)
4. The existing `KnockoutCupHandler::afterMatches()` logic (tie resolution, next-round draws) is reused

### 3.5 Handler Registration

Add to `CompetitionHandlerResolver`:

```php
'world_cup' => WorldCupHandler::class,
```

---

## Phase 4: Arcade-Mode Game Creation & Projector

### 4.1 Arcade InitGame Action

Create `App\Http\Actions\InitArcadeGame`:
- Validates: player name + national team selection
- Creates game with `game_mode = 'arcade_world_cup'`
- Minimal validation (no complex team/competition resolution)

### 4.2 Arcade Team Selection View

Create `App\Http\Views\SelectNationalTeam`:
- Loads World Cup teams grouped by confederation (UEFA, CONMEBOL, etc.) or by group
- Displays team crests, FIFA ranking, squad strength preview
- Simpler than the career mode team picker (no league tabs)

Create `resources/views/select-national-team.blade.php`:
- Grid of 32 national team cards
- Each card shows: flag/crest, team name, star rating, group assignment
- Click to select → confirm with manager name → start game

### 4.3 GameProjector: Arcade Branch

In `GameProjector::onGameCreated()`, branch on `game_mode`:

```php
if ($event->gameMode === 'arcade_world_cup') {
    $this->initializeArcadeWorldCup($gameId, $event);
    return;
}
// ... existing career mode logic
```

`initializeArcadeWorldCup()`:
1. Create `Game` record with `game_mode = 'arcade_world_cup'`
2. Copy World Cup competition teams to `CompetitionEntry`
3. Generate group stage fixtures from `groups.json` + `matchdays.json`
4. Initialize standings for each group
5. Initialize `GamePlayer` records for all national team squads
6. **Skip:** finances, budget projections, cup draws (Copa del Rey), Swiss format init

This keeps arcade initialization fast and minimal.

---

## Phase 5: Arcade AdvanceMatchday

### 5.1 Simplified Post-Match Processing

The existing `AdvanceMatchday` action needs to skip career-mode operations for arcade games. Two approaches:

**Approach A — Mode check in existing action:**
```php
if (!$game->isArcadeMode()) {
    $this->processCareerModeActions($game, ...);
}
// Always run: competition handlers, standings, notifications
```

**Approach B — Separate ArcadeAdvanceMatchday action:**
A dedicated action class that only runs the essential steps:
1. Get next match batch
2. Load players and suspensions
3. Ensure lineups
4. Simulate matches
5. Record results via event sourcing
6. Recalculate standings
7. Run competition handler `afterMatches()` (for bracket progression)
8. Check competition progress (advancement/elimination notifications)

**Recommendation: Approach A** — A single `AdvanceMatchday` with a mode guard. The core match flow is identical; only the post-match "career management" actions differ. Creating a separate class duplicates the simulation/recording logic.

### 5.2 What Gets Skipped in Arcade Mode

| Feature | Career | Arcade |
|---------|--------|--------|
| Match simulation | Yes | Yes |
| Standings update | Yes | Yes |
| Cup tie resolution | Yes | Yes |
| Player fitness/morale | Yes | Yes |
| Suspensions (cards) | Yes | Yes |
| Injuries | Yes | Yes (shorter recovery — tournament is short) |
| Transfers | Yes | **No** |
| Scouting | Yes | **No** |
| Loans | Yes | **No** |
| Contract management | Yes | **No** |
| Youth academy | Yes | **No** |
| Financial transactions | Yes | **No** |
| Transfer offer notifications | Yes | **No** |

### 5.3 Tournament Completion Detection

After each knockout match:
- `WorldCupHandler::afterMatches()` checks if the Final has been resolved
- If so, creates a `TournamentCompleted` notification with the champion
- Sets a flag on the game (e.g., `tournament_completed_at` timestamp or reuse existing season-end concept)
- Redirects to a dedicated "Tournament Complete" results screen

---

## Phase 6: UI / Views

### 6.1 New Routes

```php
// Arcade game creation
Route::get('/new-arcade-game', SelectNationalTeam::class)->name('select-national-team');
Route::post('/new-arcade-game', InitArcadeGame::class)->name('init-arcade-game');

// World Cup-specific views (under existing game routes)
Route::get('/game/{gameId}/bracket', ShowBracket::class)->name('game.bracket');
Route::get('/game/{gameId}/groups', ShowGroups::class)->name('game.groups');
Route::get('/game/{gameId}/tournament-results', ShowTournamentResults::class)->name('game.tournament-results');
```

### 6.2 New View Classes

| View | Purpose |
|------|---------|
| `SelectNationalTeam` | Team picker for arcade mode |
| `ShowBracket` | Visual knockout bracket (R16→QF→SF→Final) |
| `ShowGroups` | All group standings in a single view |
| `ShowTournamentResults` | Final results / champion celebration |

### 6.3 Modified Existing Views

| View | Changes for Arcade |
|------|-------------------|
| `ShowGame` (dashboard) | Hide transfers/finances nav items; show bracket/groups links instead |
| `ShowSquad` | Hide contract info, transfer listing, wage columns; show-only view |
| `ShowLineup` | Unchanged — formation + lineup selection works as-is |
| `ShowLiveMatch` | Unchanged — match commentary works as-is |
| `ShowMatchResults` | Unchanged — score + events display works as-is |
| `ShowCompetition` | For groups: show group standings. For bracket: show bracket view |
| Game navigation (header) | Conditionally hide: Transfers, Scouting, Finances, Calendar. Show: Groups, Bracket |

### 6.4 Landing Page / Mode Selection

Create a new entry screen or modify the existing dashboard to offer:

```
┌─────────────────────────────────┐
│         VirtuaFC                │
│                                 │
│  [🏟️ Career Mode]               │
│  Manage a Spanish club through  │
│  seasons of La Liga             │
│                                 │
│  [🏆 World Cup Arcade]          │
│  Lead your nation through the   │
│  FIFA World Cup tournament      │
└─────────────────────────────────┘
```

### 6.5 Bracket View Design

The bracket view (`resources/views/bracket.blade.php`) shows:
- A visual tournament tree: R16 (8 matches) → QF (4) → SF (2) → Final (1)
- Each node shows: team crests, score (if played), "TBD" if not yet determined
- Player's team highlighted throughout
- Mobile-responsive: horizontal scroll with sticky left column, or vertical stack on small screens

### 6.6 Groups View Design

The groups view (`resources/views/groups.blade.php`) shows:
- 8 groups in a 2×4 or 4×2 grid (responsive)
- Each group is a mini standings table: Team | P | W | D | L | GF | GA | GD | Pts
- Qualification zones highlighted (top 2 = green)
- Player's group highlighted/pinned at top

---

## Phase 7: Deployment & Configuration

### 7.1 Independent Deployment (Optional)

If desired, a separate deployment could be created:
- Same codebase, different `.env` configuration
- `APP_GAME_MODE=arcade` environment variable
- Hides career mode routes, only shows World Cup
- Different branding/theming via Tailwind config
- Could run on a subdomain: `worldcup.virtuafc.com`

Alternatively, both modes coexist in a single deployment with the mode selector.

### 7.2 Configuration

Add `config/world_cup.php`:

```php
return [
    'team_count' => 32,        // or 48
    'group_count' => 8,        // or 12
    'teams_per_group' => 4,
    'group_matchdays' => 3,
    'knockout_format' => 'single_leg',
    'extra_time' => true,
    'penalties' => true,
    'squad_size' => 26,
    'tournament_year' => 2026,
];
```

---

## Phase 8: Season End / Tournament End

### 8.1 No Traditional Season End

Arcade mode has no season transitions. Instead:
- When the Final is resolved → game is "completed"
- Player sees tournament results: champion, runner-up, golden boot, golden glove
- Option to "Play Again" (creates new game with new random groups)
- No need for any `SeasonEndProcessor` — the pipeline is skipped entirely

### 8.2 Tournament Awards

After the Final, calculate:
- **Champion** — Final winner
- **Golden Boot** — Top scorer across tournament
- **Golden Glove** — Goalkeeper with most clean sheets / fewest goals conceded
- **Best Player** — Highest average match rating (if ratings exist) or composite stat
- These use data already tracked in `game_players` (goals, assists, clean_sheets, appearances)

### 8.3 Tournament Results Screen

A dedicated celebration/results view showing:
- Tournament bracket with all results
- Award winners with player photos
- Player's team journey (every match result)
- "Play Again" button

---

## Implementation Order (Suggested)

| Step | Description | Depends On |
|------|-------------|------------|
| 1 | Migration: add `game_mode` to `games`, `group` to `game_standings` | — |
| 2 | Model changes: `Game::isArcadeMode()`, constants | Step 1 |
| 3 | Build World Cup reference data (`app:build-world-cup-data` command) | Existing player data |
| 4 | Seeder profile: `world_cup` in `SeedReferenceData` | Step 3 |
| 5 | `WorldCupHandler` — group stage logic | Step 2 |
| 6 | `WorldCupHandler` — knockout bracket generation | Step 5 |
| 7 | `WorldCupConfig` competition config | Step 5 |
| 8 | `GameProjector` — arcade branch for game creation | Steps 2, 4 |
| 9 | `AdvanceMatchday` — arcade mode guards | Step 2 |
| 10 | `CreateGame` command/event — carry `game_mode` | Step 2 |
| 11 | Team selection UI (`SelectNationalTeam`, blade template) | Step 4 |
| 12 | Mode selection landing page | Step 11 |
| 13 | `ShowGroups` view + blade template | Steps 5, 8 |
| 14 | `ShowBracket` view + blade template | Steps 6, 8 |
| 15 | Navigation: conditionally hide career items in arcade | Step 2 |
| 16 | `ShowSquad` — hide contracts/wages/transfers in arcade | Step 2 |
| 17 | Tournament completion detection | Step 6 |
| 18 | Tournament awards + results screen | Step 17 |
| 19 | "Play Again" flow | Step 18 |
| 20 | Config file `config/world_cup.php` | — |
| 21 | Optional: separate deployment config | Step 20 |
| 22 | Tests for all new components | All steps |

---

## Files to Create (New)

| File | Purpose |
|------|---------|
| `data/2025/WC/teams.json` | National team rosters |
| `data/2025/WC/groups.json` | Group assignments |
| `data/2025/WC/matchdays.json` | Tournament calendar |
| `data/2025/WC/rounds.json` | Knockout round templates |
| `app/Console/Commands/BuildWorldCupData.php` | Data assembly command |
| `app/Game/Handlers/WorldCupHandler.php` | Tournament competition handler |
| `app/Game/Competitions/WorldCupConfig.php` | Competition config |
| `app/Http/Actions/InitArcadeGame.php` | Arcade game creation |
| `app/Http/Views/SelectNationalTeam.php` | Team picker view |
| `app/Http/Views/ShowBracket.php` | Bracket view |
| `app/Http/Views/ShowGroups.php` | Groups overview |
| `app/Http/Views/ShowTournamentResults.php` | Final results |
| `resources/views/select-national-team.blade.php` | Team picker UI |
| `resources/views/bracket.blade.php` | Bracket UI |
| `resources/views/groups.blade.php` | Groups UI |
| `resources/views/tournament-results.blade.php` | Results UI |
| `config/world_cup.php` | Tournament config |
| `database/migrations/xxxx_add_game_mode_to_games.php` | Schema change |
| `database/migrations/xxxx_add_group_to_game_standings.php` | Schema change |
| `lang/es/world_cup.php` | Spanish translations |
| `tests/Feature/WorldCupHandlerTest.php` | Handler tests |
| `tests/Feature/ArcadeGameCreationTest.php` | Game creation tests |
| `tests/Unit/WorldCupBracketTest.php` | Bracket generation tests |

## Files to Modify (Existing)

| File | Changes |
|------|---------|
| `app/Models/Game.php` | Add `game_mode` constants, helper methods |
| `app/Game/Commands/CreateGame.php` | Add `gameMode` parameter |
| `app/Game/Events/GameCreated.php` | Add `gameMode` field |
| `app/Game/Game.php` (aggregate) | Pass `gameMode` in `create()` |
| `app/Game/GameProjector.php` | Branch on mode in `onGameCreated()` |
| `app/Http/Actions/AdvanceMatchday.php` | Guard career-only actions with `isArcadeMode()` |
| `app/Http/Actions/InitGame.php` | Minor: accept `game_mode` or keep separate action |
| `app/Game/Services/CompetitionHandlerResolver.php` | Register `WorldCupHandler` |
| `app/Console/Commands/SeedReferenceData.php` | Add `world_cup` profile |
| `routes/web.php` | Add arcade routes |
| `resources/views/components/game-header.blade.php` | Conditional navigation |
| `resources/views/squad.blade.php` | Hide financial columns in arcade |
| `resources/views/dashboard.blade.php` | Mode selector |

---

## Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Not enough players per nationality | Weak squads for smaller nations | Fill gaps with generated players (random abilities within range for team's FIFA ranking) |
| Group stage tiebreaker complexity | Incorrect advancement | Implement FIFA tiebreaker rules in `StandingsCalculator` or `WorldCupHandler` |
| Bracket generation correctness | Wrong matchups | Thorough unit tests with known bracket structures |
| Existing tests break from `game_mode` | CI failures | Default value `'career'` ensures backward compatibility |
| Performance with 32 teams × 26 players | Slow game creation | Already handles 400+ players for ESP1+ESP2+European, so 832 players is fine |

---

## Scope Summary

- **Reused as-is:** Match simulator, lineup management, formation/mentality system, player fitness/morale, cards/suspensions, injuries, live match view, match results view, notification system
- **Modified:** Game creation flow, matchday advancement, navigation, squad view, projector
- **New:** World Cup handler, bracket generation, group standings, national team data, team picker, bracket UI, groups UI, tournament results
- **Excluded from arcade:** Transfers, scouting, loans, contracts, wages, finances, youth academy, season transitions, promotion/relegation
