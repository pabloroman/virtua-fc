# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

VirtuaFC is a football manager simulation game built with Laravel 12. Players manage Spanish football teams (La Liga/Segunda División) through seasons, handling squad selection, transfers, and competitions including the Copa del Rey and European competitions (Champions League, Europa League, Conference League).

The frontend uses Blade templates with Tailwind CSS and Alpine.js. The app defaults to Spanish (`APP_LOCALE=es`).

## Development Commands

```bash
# Run all services (server, queue, vite, logs)
composer dev

# Run tests
php artisan test

# Run a single test
php artisan test --filter=TestClassName
php artisan test tests/Feature/SpecificTest.php

# Seed reference data (teams, players, competitions)
php artisan app:seed-reference-data
php artisan app:seed-reference-data --fresh  # Reset and re-seed

# Create a test game for local development
php artisan app:create-test-game

# Simulate a match (debugging)
php artisan app:simulate-match

# Simulate a full season
php artisan app:simulate-season

# Clear config cache after changing config files
php artisan config:clear
```

**Important:** The queue worker must be running for background jobs (e.g. game setup). `composer dev` handles this automatically via `php artisan queue:listen --tries=1`.

## Architecture

### HTTP Layer Pattern

Uses invokable single-action classes instead of traditional controllers:

- **Actions:** `App\Http\Actions\*` - handle form submissions and game commands (21 classes)
- **Views:** `App\Http\Views\*` - prepare data for Blade templates (19 classes)

Authentication is handled by Laravel Breeze controllers in `App\Http\Controllers\Auth\`.

Example: `ShowGame` → `views/game.blade.php`, `AdvanceMatchday` handles playing matches.

### Modular Monolith Architecture

The codebase follows a **modular monolith** pattern. Domain logic is organized into 9 modules under `app/Modules/`, each with its own services, contracts, DTOs, and events:

| Module | Purpose | Key services |
|--------|---------|-------------|
| **Match** | Match simulation engine | `MatchSimulator`, `MatchdayService`, `CupTieResolver`, handlers |
| **Lineup** | Tactical layer | `LineupService`, `SubstitutionService`, `FormationRecommender` |
| **Squad** | Player management | `PlayerDevelopmentService`, `PlayerGeneratorService`, `InjuryService` |
| **Transfer** | Market operations | `TransferService`, `ContractService`, `LoanService`, `ScoutingService` |
| **Competition** | Structure & config | `CountryConfig`, `StandingsCalculator`, `CupDrawService`, handlers config |
| **Finance** | Economic model | `BudgetProjectionService`, `SeasonSimulationService` |
| **Season** | Lifecycle orchestration | `SeasonEndPipeline`, `GameCreationService`, all 21 processors |
| **Notification** | In-game messaging | `NotificationService`, event listeners |
| **Academy** | Youth development | `YouthAcademyService` |

**Dependency direction:** Season (orchestrator) → Match, Transfer, Finance → Squad, Competition → Notification (leaf). No circular dependencies.

Models stay in `app/Models/` (shared). The HTTP layer (`Actions/Views`) stays in `app/Http/` as thin orchestrators.

### Pluggable Competition Handlers

Different competition types use handlers implementing `App\Modules\Competition\Contracts\CompetitionHandler`:

- `LeagueHandler` - standard league with standings
- `KnockoutCupHandler` - Copa del Rey bracket/draws
- `LeagueWithPlayoffHandler` - league with playoff rounds
- `SwissFormatHandler` - Champions League Swiss-system format

Resolved via `CompetitionHandlerResolver` based on competition's `handler_type` field.

Competition-specific configuration (revenue rates, commercial per seat, etc.) lives in `App\Modules\Competition\Configs\*`:
- `LaLigaConfig`, `LaLiga2Config`, `DefaultLeagueConfig`
- `ChampionsLeagueConfig`, `EuropaLeagueConfig`, `ConferenceLeagueConfig`

### Season End Pipeline

End-of-season processing uses ordered processors implementing `App\Modules\Season\Contracts\SeasonEndProcessor`:

```php
// Processors run in priority order (lower = earlier)
SeasonArchiveProcessor (0)
LoanReturnProcessor (3)
ContractExpirationProcessor (5)
PreContractTransferProcessor (5)
ContractRenewalProcessor (6)
PlayerRetirementProcessor (7)
SquadReplenishmentProcessor (8)
PlayerDevelopmentProcessor (10)
SeasonSettlementProcessor (15)
StatsResetProcessor (20)
TransferMarketResetProcessor (20)
SeasonSimulationProcessor (24)
SupercupQualificationProcessor (25)
PromotionRelegationProcessor (26)
LeagueFixtureProcessor (30)
StandingsResetProcessor (40)
BudgetProjectionProcessor (50)
YouthAcademyProcessor (55)
UefaQualificationProcessor (105)
ContinentalAndCupInitProcessor (106)
OnboardingResetProcessor (110)
```

New processors can be added to `SeasonEndPipeline` without modifying existing code.

### Financial Model

Uses projection-based budgeting (not running balance):

- `GameFinances` - season projections (revenue, wages) calculated at season start
- `GameInvestment` - budget allocation (transfer budget, infrastructure tiers)
- `FinancialTransaction` - records income/expense for season-end reconciliation

Revenue rates (commercial per seat, matchday per seat) are defined per competition config (`CompetitionConfig::getCommercialPerSeat()`, `getRevenuePerSeat()`), not on `ClubProfile`. Commercial revenue grows over seasons via position-based multipliers in `config/finances.php`.

**Important:** `currentFinances` and `currentInvestment` relationships use `$this->season` in their queries. Always use lazy loading (access after model load), not eager loading with `with()`.

### Notification System

`GameNotification` model with `NotificationService` handles in-game notifications (transfer results, contract events, season milestones). Notifications are per-game and displayed in the UI.

## Key Files

| Purpose | Location |
|---------|----------|
| Game creation | `app/Modules/Season/Services/GameCreationService.php` |
| Match simulator | `app/Modules/Match/Services/MatchSimulator.php` |
| Simulation config | `config/match_simulation.php` |
| Season end pipeline | `app/Modules/Season/Services/SeasonEndPipeline.php` |
| Financial config | `config/finances.php` |
| Transfer service | `app/Modules/Transfer/Services/TransferService.php` |
| Player development | `app/Modules/Squad/Services/PlayerDevelopmentService.php` |
| Scouting service | `app/Modules/Transfer/Services/ScoutingService.php` |
| Youth academy | `app/Modules/Academy/Services/YouthAcademyService.php` |
| Loan service | `app/Modules/Transfer/Services/LoanService.php` |
| Routes | `routes/web.php` |

## Directory Structure

```
app/
├── Console/Commands/     # Artisan commands (seed, simulate, beta invites)
├── Modules/
│   ├── Match/            # Match simulation engine
│   │   ├── Services/     # MatchSimulator, MatchdayService, CupTieResolver, etc.
│   │   ├── Handlers/     # LeagueHandler, KnockoutCupHandler, SwissFormatHandler
│   │   ├── Events/       # MatchFinalized, CupTieResolved
│   │   ├── Listeners/    # UpdateLeagueStandings, AwardCupPrizeMoney, etc.
│   │   └── DTOs/         # MatchResult, MatchEventData, ResimulationResult
│   ├── Lineup/           # Tactical layer
│   │   ├── Services/     # LineupService, SubstitutionService, TacticalChangeService
│   │   └── Enums/        # Formation, Mentality
│   ├── Squad/            # Player management
│   │   ├── Services/     # PlayerDevelopmentService, PlayerGeneratorService, InjuryService
│   │   └── DTOs/         # GeneratedPlayerData
│   ├── Transfer/         # Market operations
│   │   └── Services/     # TransferService, ContractService, LoanService, ScoutingService
│   ├── Competition/      # Structure & configuration
│   │   ├── Services/     # CountryConfig, StandingsCalculator, CupDrawService, etc.
│   │   ├── Contracts/    # CompetitionHandler, CompetitionConfig, PlayoffGenerator
│   │   ├── Configs/      # LaLigaConfig, ChampionsLeagueConfig, etc.
│   │   ├── Playoffs/     # ESP2PlayoffGenerator, PlayoffGeneratorFactory
│   │   ├── Promotions/   # ConfigDrivenPromotionRule, PromotionRelegationFactory
│   │   └── DTOs/         # PlayoffRoundConfig
│   ├── Finance/          # Economic model
│   │   └── Services/     # BudgetProjectionService, SeasonSimulationService
│   ├── Season/           # Lifecycle orchestration
│   │   ├── Services/     # SeasonEndPipeline, GameCreationService, etc.
│   │   ├── Processors/   # 21 season-end processors
│   │   ├── Contracts/    # SeasonEndProcessor
│   │   ├── DTOs/         # SeasonTransitionData
│   │   └── Jobs/         # SetupNewGame, SetupTournamentGame
│   ├── Notification/     # In-game messaging
│   │   ├── Services/     # NotificationService
│   │   └── Listeners/    # SendMatchNotifications, SendCupTieNotifications
│   └── Academy/          # Youth development
│       ├── Services/     # YouthAcademyService
│       └── Listeners/    # GenerateInitialAcademyBatch
├── Http/
│   ├── Actions/          # Form handlers (invokable, 32 classes)
│   ├── Controllers/Auth/ # Laravel Breeze auth controllers
│   ├── Middleware/        # EnsureGameOwnership, RequireInviteForRegistration
│   ├── Requests/         # Form requests (LoginRequest, ProfileUpdateRequest)
│   └── Views/            # View data preparation (invokable, 24 classes)
├── Jobs/                 # Background jobs (beta feedback)
├── Mail/                 # Mailable classes (beta invites)
├── Models/               # Eloquent models (27 models, shared across modules)
├── Providers/            # Service providers (App, Horizon, Telescope)
├── Support/              # Utilities (Money, PositionMapper, PositionSlotMapper, CountryCodeMapper)
└── View/Components/      # Blade layout components

data/                     # Reference JSON (teams, players, fixtures)
├── 2025/
│   ├── ESP1/             # La Liga
│   ├── ESP2/             # Segunda División
│   ├── ESPCUP/           # Copa del Rey
│   ├── ESPSUP/           # Supercopa de España
│   ├── UCL/              # Champions League
│   └── EUR/              # European club data by country
├── TEST1/, TESTCUP/      # Test competition data
└── academy/              # Youth academy player data

docs/game-systems/        # Game systems documentation (12 documents)
landing/                  # Cloudflare Workers landing page (separate project)

resources/
├── css/app.css           # Tailwind CSS styles
├── js/                   # Alpine.js app + live-match handler
└── views/                # Blade templates (56 templates)

config/
├── match_simulation.php  # Tunable simulation parameters
├── finances.php          # Financial system config
├── beta.php              # Beta mode configuration
├── horizon.php           # Queue monitoring (Laravel Horizon)
└── telescope.php         # Debugging (Laravel Telescope)
```

## Database

- **Production uses PostgreSQL** (Neon via Laravel Cloud), development uses SQLite
- **All raw SQL must be PostgreSQL-compatible.** Avoid SQLite-only functions like `strftime()`. When raw SQL is unavoidable, detect the driver with `$query->getQuery()->getConnection()->getDriverName()` and branch for `pgsql` vs `sqlite`. Prefer Eloquent query builder over raw SQL whenever possible to avoid dialect issues.
- UUID primary keys throughout
- Key tables: `games`, `game_players`, `game_matches`, `game_standings`, `game_finances`, `game_investments`, `game_notifications`, `loans`, `scout_reports`, `transfer_offers`, `season_archives`, `simulated_seasons`, `cup_ties`, `player_suspensions`, `financial_transactions`, `competition_entries`, `competition_teams`, etc.
- **No wall-clock timestamps on game models.** Time in VirtuaFC follows the game-universe calendar (seasons, matchdays, `current_date` on `Game`), not real-world wall-clock time. Unless explicitly needed (e.g. `users` for auth), models should set `public $timestamps = false` and omit `$table->timestamps()` from migrations.

## Models

Key Eloquent models (24 total):

| Model | Purpose |
|-------|---------|
| `Game` | Main game instance |
| `GamePlayer` | Player within a game |
| `GameMatch` | Match within a game |
| `GameStanding` | League standings |
| `GameFinances` | Season financial projections |
| `GameInvestment` | Budget allocation |
| `GameNotification` | In-game notifications |
| `ClubProfile` | Club-specific data |
| `Competition` | Competition definitions |
| `CompetitionEntry` | Team entries in competitions |
| `CompetitionTeam` | Teams in competitions |
| `CupTie` | Cup match pairings |
| `FinancialTransaction` | Income/expense records |
| `Loan` | Player loans |
| `MatchEvent` | In-match events (goals, cards) |
| `Player` | Reference player data |
| `PlayerSuspension` | Card suspensions |
| `ScoutReport` | Scouting results |
| `SeasonArchive` | Historical season data |
| `SimulatedSeason` | Simulated AI season results |
| `Team` | Reference team data |
| `TransferOffer` | Transfer bids |
| `InviteCode` | Beta invite codes |
| `User` | User accounts |

## Testing

Tests are in `tests/` with standard PHPUnit structure. Run specific tests with `--filter`:

```bash
php artisan test --filter=MatchSimulatorTest
```

**Test structure:**
- `tests/Feature/` - Integration tests (matchday advancement, notifications, player generation, retirement)
- `tests/Feature/Auth/` - Authentication flow tests (registration, login, password reset)
- `tests/Unit/` - Unit tests (competition handlers, fixture generation, Swiss draw)

## Configuration

Match simulation can be tuned without code changes via `config/match_simulation.php`:
- Base goals, home advantage, strength multipliers
- Performance variance (randomness)
- Event probabilities (cards, injuries)

Clear cache after changes: `php artisan config:clear`

## Tech Stack

**Backend:** PHP 8.4, Laravel 12, Laravel Horizon, Laravel Telescope, Resend (email)

**Frontend:** Vite 5, Tailwind CSS 3, Alpine.js 3, Alpine Tooltip, Axios

**Dev tools:** Laravel Breeze (auth), Laravel Pint (code style), Laravel Pail (log tailing), PHPUnit 11

## Internationalization (i18n)

The application uses Spanish as the default language (`APP_LOCALE=es`). All user-facing strings must be translatable. **Both Spanish (`lang/es/`) and English (`lang/en/`) translations are maintained — every new translation key must be added to both languages.**

### Translation Files

```
lang/
├── es/                # Spanish (default)
│   ├── app.php            # General UI (buttons, labels, navigation)
│   ├── auth.php           # Authentication
│   ├── beta.php           # Beta mode strings
│   ├── cup.php            # Copa del Rey / cup competition terms
│   ├── finances.php       # Financial terms
│   ├── game.php           # Game-specific terms (season, matchday, etc.)
│   ├── messages.php       # Flash messages (success, error, info)
│   ├── notifications.php  # In-game notification strings
│   ├── season.php         # Season end, awards, promotions
│   ├── squad.php          # Squad/player related
│   └── transfers.php      # Transfers, scouting, contracts
└── en/                # English (mirrors es/ structure)
    ├── app.php
    ├── auth.php
    ├── beta.php
    ├── cup.php
    ├── finances.php
    ├── game.php
    ├── messages.php
    ├── notifications.php
    ├── season.php
    ├── squad.php
    └── transfers.php
```

### Coding Standards

**Blade templates:** Always wrap user-facing strings in `__()`:

```blade
{{-- Static strings --}}
<h3>{{ __('squad.title') }}</h3>
<button>{{ __('app.save') }}</button>

{{-- With parameters --}}
<h3>{{ __('squad.title', ['team' => $game->team->name]) }}</h3>
<p>{{ __('game.expires_in', ['days' => $daysLeft]) }}</p>

{{-- Pluralization --}}
<span>{{ trans_choice('game.weeks_remaining', $weeks, ['count' => $weeks]) }}</span>
```

**Action files (flash messages):** Use translation keys with parameters:

```php
// Before
->with('success', "Transfer complete! {$playerName} joined.");

// After
->with('success', __('messages.transfer_complete', ['player' => $playerName]));
```

### Key Patterns

| Category | Key Format | Example |
|----------|-----------|---------|
| Buttons/actions | `app.*` | `app.save`, `app.confirm` |
| Navigation | `app.*` | `app.dashboard`, `app.squad` |
| Game terms | `game.*` | `game.season`, `game.matchday` |
| Squad labels | `squad.*` | `squad.goalkeepers`, `squad.fitness` |
| Transfer terms | `transfers.*` | `transfers.bid_rejected` |
| Finance terms | `finances.*` | `finances.transfer_budget` |
| Flash messages | `messages.*` | `messages.player_listed` |
| Season end | `season.*` | `season.champion`, `season.relegated` |
| Cup terms | `cup.*` | `cup.round`, `cup.draw` |
| Notifications | `notifications.*` | `notifications.transfer_complete` |

### Adding New Strings

1. Add the key and Spanish translation to the appropriate file in `lang/es/`
2. Add the corresponding English translation to the matching file in `lang/en/`
3. Use the key in your blade template or PHP code
4. Test that the translation displays correctly in both languages

## UI/UX Guidelines

When working on UI/UX tasks, implement working code (Blade/Tailwind CSS/Alpine.js) that is:

- **Production-grade and functional** - Code must work correctly, not just look good in a mockup
- **Visually striking and memorable** - Go beyond defaults; create interfaces that feel polished and intentional
- **Cohesive with a clear aesthetic point-of-view** - Maintain a consistent design language across all pages
- **Meticulously refined in every detail** - Pay extra attention to component reusability and ensure visual elements are coherent and uniform across the application

## Mobile Responsiveness (Required)

**Every new UI feature or screen must be mobile-friendly from the start.** This is not optional — a significant portion of users play on phones and tablets. Always verify your layouts work at 375px (phone) and 768px (tablet) widths.

### Core Principles

- **Mobile-first Tailwind**: Write base styles for mobile, then add `md:` or `lg:` prefixes for larger screens. Never write desktop-only layouts that require a separate mobile fix later.
- **Touch targets**: All interactive elements (buttons, links, toggles) must be at least 44px tall. All button components already include `min-h-[44px]`.
- **No horizontal overflow**: Pages must not scroll horizontally on mobile. Wrap data tables in `overflow-x-auto` and hide non-essential columns with `hidden md:table-cell`.

### Responsive Patterns in Use

Follow these established patterns when building new UI:

| Pattern | Usage | Example |
|---------|-------|---------|
| Responsive grid | Multi-column layouts | `grid-cols-1 md:grid-cols-3` (never bare `grid-cols-3`) |
| Column span | Sidebar/main splits | `md:col-span-2` (never bare `col-span-2`) |
| Flex stacking | Header bars, card rows | `flex flex-col md:flex-row md:items-center md:justify-between gap-2` |
| Column hiding | Data tables | `hidden md:table-cell` on non-essential columns |
| Sticky columns | Wide scrollable tables | `sticky left-0 bg-white z-10` on name/position columns |
| Scrollable tabs | Horizontal tab navs | `overflow-x-auto scrollbar-hide` on container, `shrink-0` on items |
| Responsive sizing | Logos, images, scores | `w-10 h-10 md:w-14 md:h-14 shrink-0` |
| Responsive text | Titles, scores | `text-sm md:text-xl` or `text-3xl md:text-5xl` |
| Responsive spacing | Gaps, padding | `gap-2 md:gap-6`, `p-4 md:p-8` |
| Truncated text | Long names on mobile | `truncate` class on team/player names |
| Mobile tab panels | Complex dual-panel UIs | Alpine.js tabs that show one panel at a time on mobile, both on desktop |

### Navigation

The app uses a **slide-out drawer** on mobile (hamburger menu in `game-header.blade.php`). Desktop navigation is preserved with `hidden md:flex`. When adding new navigation items, add them to **both** the desktop nav and the mobile drawer.

### Data Tables Checklist

When creating or modifying a data table:

1. Wrap the table in `<div class="overflow-x-auto">...</div>`
2. Identify which columns are essential on mobile (usually 2-4: name, key stat, action)
3. Add `hidden md:table-cell` to non-essential `<th>` and `<td>` elements
4. Consider making the first column (name/identifier) sticky if the table is very wide
5. Ensure touch targets in action columns meet the 44px minimum

### Font Scaling

The app uses a custom root font-size override (`resources/css/app.css`): 14px on mobile vs the default ~20px (`1.25rem` base in Tailwind config). All `rem`-based sizes scale proportionally. Do not use fixed `px` values for font sizes — use Tailwind's `text-*` utilities so they participate in this scaling.

### Things to Avoid

- **Never use bare `grid-cols-N`** (where N > 1) without a mobile-first base like `grid-cols-1`
- **Never use bare `col-span-N`** without an `md:` or `lg:` prefix
- **Never set fixed widths** that would cause overflow on 375px screens
- **Never hide critical game actions on mobile** — the user must be able to play the full game on their phone
- **Avoid hover-only interactions** — anything behind `:hover` must also be accessible via tap/click

## Backend Performance

When implementing backend code, pay attention to performance and scalability:

- **Prevent slow queries** - Avoid N+1 problems; use eager loading (`with()`) where appropriate (but note the `currentFinances`/`currentInvestment` exception above)
- **Use database indices correctly** - Ensure queries filter on indexed columns; add indices for new columns used in WHERE/JOIN clauses
- **Optimize algorithms** - Avoid unnecessary loops, redundant computations, and excessive memory usage
- **Leverage Laravel features** - Use chunking for large datasets, queue heavy work, and cache expensive computations where appropriate

## Game Systems Documentation

The `docs/game-systems/` directory contains high-level documentation of all game mechanics (match simulation, player development, finances, transfers, injuries, academy, etc.). These documents describe **what each system does and how systems connect** — they are not a mirror of exact formulas or config values. Tuning a parameter (e.g., changing `base_goals` from 1.3 to 1.4) does not require a doc update. Changing the nature of a system (e.g., replacing Poisson scoring with a different model) does.

**When to update docs:**

- **New game system or mechanic**: Add a document describing what it does, why, and where the code lives
- **Structural changes**: A system works fundamentally differently (new algorithm, new inputs, removed mechanic)
- **New season processors**: Update `season-lifecycle.md`
- **Renamed/moved services**: Update file references

**When NOT to update docs:**

- Tweaking config values, thresholds, multipliers, or weights
- Adjusting formulas without changing what they conceptually do
- Bug fixes that don't change how a system works

The documentation index is in `docs/game-systems/README.md`. When adding a new document, add it to the index.

## Code Quality

### No Dead Code

- **Never leave dead code:** Remove functions, methods, and classes that are not called from anywhere
- **Clean up after refactoring:** When refactoring, ensure you remove any code that becomes unused
- **Don't create unused functions:** Only write functions that are actually called by other code
- **Remove commented-out code:** Don't leave commented-out code blocks in the codebase
