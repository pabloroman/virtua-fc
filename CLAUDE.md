# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

VirtuaFC is a football manager simulation game built with Laravel 12 and Spatie Event Sourcing. Players manage Spanish football teams (La Liga/Segunda División) through seasons, handling squad selection, transfers, and competitions including the Copa del Rey and European competitions (Champions League, Europa League, Conference League).

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

**Important:** The queue worker must be running for event sourcing to work. `composer dev` handles this automatically via `php artisan queue:listen --tries=1`.

## Architecture

### Event Sourcing Flow

```
HTTP Request → Action → Command → Aggregate → Event → Projector → Read Models
```

- **Aggregate Root:** `App\Game\Game` (extends Spatie AggregateRoot)
- **Commands:** `App\Game\Commands\*` sent via `Game::retrieve($uuid)->commandMethod()`
- **Events:** `App\Game\Events\*` recorded by aggregate, stored in `stored_events` table
- **Projector:** `App\Game\GameProjector` listens to events and updates read model tables

### HTTP Layer Pattern

Uses invokable single-action classes instead of traditional controllers:

- **Actions:** `App\Http\Actions\*` - handle form submissions and game commands (21 classes)
- **Views:** `App\Http\Views\*` - prepare data for Blade templates (19 classes)

Authentication is handled by Laravel Breeze controllers in `App\Http\Controllers\Auth\`.

Example: `ShowGame` → `views/game.blade.php`, `AdvanceMatchday` handles playing matches.

### Pluggable Competition Handlers

Different competition types use handlers implementing `App\Game\Contracts\CompetitionHandler`:

- `LeagueHandler` - standard league with standings
- `KnockoutCupHandler` - Copa del Rey bracket/draws
- `LeagueWithPlayoffHandler` - league with playoff rounds
- `SwissFormatHandler` - Champions League Swiss-system format

Resolved via `CompetitionHandlerResolver` based on competition's `handler_type` field.

Competition-specific configuration (revenue rates, commercial per seat, etc.) lives in `App\Game\Competitions\*`:
- `LaLigaConfig`, `LaLiga2Config`, `DefaultLeagueConfig`
- `ChampionsLeagueConfig`, `EuropaLeagueConfig`, `ConferenceLeagueConfig`

### Season End Pipeline

End-of-season processing uses ordered processors implementing `App\Game\Contracts\SeasonEndProcessor`:

```php
// Processors run in priority order (lower = earlier)
LoanReturnProcessor (3)
SeasonArchiveProcessor (5)
ContractExpirationProcessor (5)
PreContractTransferProcessor (5)
ContractRenewalProcessor (6)
PlayerRetirementProcessor (7)
PlayerDevelopmentProcessor (10)
SeasonSettlementProcessor (15)
StatsResetProcessor (20)
SeasonSimulationProcessor (24)
SupercopaQualificationProcessor (25)
PromotionRelegationProcessor (26)
FixtureGenerationProcessor (30)
StandingsResetProcessor (40)
BudgetProjectionProcessor (50)
YouthAcademyProcessor (55)
UefaQualificationProcessor (105)
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
| Game aggregate | `app/Game/Game.php` |
| Event projector | `app/Game/GameProjector.php` |
| Match simulator | `app/Game/Services/MatchSimulator.php` |
| Simulation config | `config/match_simulation.php` |
| Season end pipeline | `app/Game/Services/SeasonEndPipeline.php` |
| Financial config | `config/finances.php` |
| Transfer service | `app/Game/Services/TransferService.php` |
| Player development | `app/Game/Services/PlayerDevelopmentService.php` |
| Scouting service | `app/Game/Services/ScoutingService.php` |
| Youth academy | `app/Game/Services/YouthAcademyService.php` |
| Loan service | `app/Game/Services/LoanService.php` |
| Routes | `routes/web.php` |

## Directory Structure

```
app/
├── Console/Commands/     # Artisan commands (seed, simulate, beta invites)
├── Game/
│   ├── Commands/         # Aggregate commands
│   ├── Competitions/     # Per-league config classes (LaLigaConfig, etc.)
│   ├── Contracts/        # Interfaces (CompetitionHandler, SeasonEndProcessor, etc.)
│   ├── DTO/              # Data Transfer Objects (MatchResult, SeasonTransitionData, etc.)
│   ├── Enums/            # PHP enums (Formation, Mentality)
│   ├── Events/           # Domain events
│   ├── Handlers/         # Competition handlers
│   ├── Playoffs/         # Playoff generation logic
│   ├── Processors/       # Season end processors (18 classes)
│   ├── Promotions/       # Promotion/relegation rules
│   └── Services/         # Business logic (30+ services)
├── Http/
│   ├── Actions/          # Form handlers (invokable, 21 classes)
│   ├── Controllers/Auth/ # Laravel Breeze auth controllers
│   ├── Middleware/        # EnsureGameOwnership, RequireInviteForRegistration
│   ├── Requests/         # Form requests (LoginRequest, ProfileUpdateRequest)
│   └── Views/            # View data preparation (invokable, 19 classes)
├── Jobs/                 # Background jobs (beta feedback)
├── Mail/                 # Mailable classes (beta invites)
├── Models/               # Eloquent models (25 models)
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

docs/game-systems/        # Game design documentation (9 documents)
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

- Uses SQLite by default
- UUID primary keys throughout
- Event sourcing tables: `stored_events`, `snapshots`
- Read models: `games`, `game_players`, `game_matches`, `game_standings`, `game_finances`, `game_investments`, `game_notifications`, `loans`, `scout_reports`, `transfer_offers`, `season_archives`, `simulated_seasons`, `cup_ties`, `cup_round_templates`, `player_suspensions`, `financial_transactions`, `competition_entries`, `competition_teams`, etc.
- 52 migrations total

## Models

Key Eloquent models (25 total):

| Model | Purpose |
|-------|---------|
| `Game` | Main game instance (read model) |
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
| `CupRoundTemplate` | Cup round structure |
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

**Backend:** PHP 8.4, Laravel 12, Spatie Event Sourcing 7.12, Laravel Horizon, Laravel Telescope, Resend (email)

**Frontend:** Vite 5, Tailwind CSS 3, Alpine.js 3, Alpine Tooltip, Axios

**Dev tools:** Laravel Breeze (auth), Laravel Pint (code style), Laravel Pail (log tailing), PHPUnit 11

## Internationalization (i18n)

The application uses Spanish as the default language. All user-facing strings must be translatable.

### Translation Files

```
lang/es/
├── app.php            # General UI (buttons, labels, navigation)
├── auth.php           # Authentication
├── beta.php           # Beta mode strings
├── cup.php            # Copa del Rey / cup competition terms
├── finances.php       # Financial terms
├── game.php           # Game-specific terms (season, matchday, etc.)
├── messages.php       # Flash messages (success, error, info)
├── notifications.php  # In-game notification strings
├── season.php         # Season end, awards, promotions
├── squad.php          # Squad/player related
└── transfers.php      # Transfers, scouting, contracts
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
2. Use the key in your blade template or PHP code
3. Test that the translation displays correctly

## UI/UX Guidelines

When working on UI/UX tasks, implement working code (Blade/Tailwind CSS/Alpine.js) that is:

- **Production-grade and functional** - Code must work correctly, not just look good in a mockup
- **Visually striking and memorable** - Go beyond defaults; create interfaces that feel polished and intentional
- **Cohesive with a clear aesthetic point-of-view** - Maintain a consistent design language across all pages
- **Meticulously refined in every detail** - Pay extra attention to component reusability and ensure visual elements are coherent and uniform across the application

## Code Quality

### No Dead Code

- **Never leave dead code:** Remove functions, methods, and classes that are not called from anywhere
- **Clean up after refactoring:** When refactoring, ensure you remove any code that becomes unused
- **Don't create unused functions:** Only write functions that are actually called by other code
- **Remove commented-out code:** Don't leave commented-out code blocks in the codebase
