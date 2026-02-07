# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

VirtuaFC is a football manager simulation game built with Laravel 11 and Spatie Event Sourcing. Players manage Spanish football teams (La Liga/Segunda División) through seasons, handling squad selection, transfers, and competitions including the Copa del Rey.

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

# Clear config cache after changing config files
php artisan config:clear
```

**Important:** The queue worker must be running for event sourcing to work. `composer dev` handles this automatically.

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

- **Actions:** `App\Http\Actions\*` - handle form submissions and game commands
- **Views:** `App\Http\Views\*` - prepare data for blade templates

Example: `ShowGame` → `views/game.blade.php`, `AdvanceMatchday` handles playing matches

### Pluggable Competition Handlers

Different competition types use handlers implementing `App\Game\Contracts\CompetitionHandler`:

- `LeagueHandler` - standard league with standings
- `KnockoutCupHandler` - Copa del Rey bracket/draws
- `LeagueWithPlayoffHandler` - league with playoff rounds

Resolved via `CompetitionHandlerResolver` based on competition's `handler_type` field.

### Season End Pipeline

End-of-season processing uses ordered processors implementing `App\Game\Contracts\SeasonEndProcessor`:

```php
// Processors run in priority order (lower = earlier)
FixtureGenerationProcessor (10)
StandingsResetProcessor (20)
LoanReturnProcessor (30)
ContractExpirationProcessor (50)
PlayerDevelopmentProcessor (60)
SeasonArchiveProcessor (70)
```

New processors can be added to `SeasonEndPipeline` without modifying existing code.

### Financial Model

Uses projection-based budgeting (not running balance):

- `GameFinances` - season projections (revenue, wages) calculated at season start
- `GameInvestment` - budget allocation (transfer budget, infrastructure tiers)
- `FinancialTransaction` - records income/expense for season-end reconciliation

**Important:** `currentFinances` and `currentInvestment` relationships use `$this->season` in their queries. Always use lazy loading (access after model load), not eager loading with `with()`.

## Key Files

| Purpose | Location |
|---------|----------|
| Game aggregate | `app/Game/Game.php` |
| Event projector | `app/Game/GameProjector.php` |
| Match simulator | `app/Game/Services/MatchSimulator.php` |
| Simulation config | `config/match_simulation.php` |
| Season end pipeline | `app/Game/Services/SeasonEndPipeline.php` |
| Routes | `routes/web.php` |

## Directory Structure

```
app/
├── Game/
│   ├── Commands/         # Aggregate commands
│   ├── Events/           # Domain events
│   ├── Services/         # Business logic (MatchSimulator, TransferService, etc.)
│   ├── Handlers/         # Competition handlers
│   ├── Processors/       # Season end processors
│   └── Contracts/        # Interfaces
├── Http/
│   ├── Actions/          # Form handlers (invokable)
│   └── Views/            # View data preparation (invokable)
├── Models/               # Eloquent models
└── Support/              # Utilities (Money, PositionMapper)

data/                     # Reference JSON (teams, players, fixtures)
docs/game-systems/        # Game design documentation
config/match_simulation.php  # Tunable simulation parameters
```

## Database

- Uses SQLite by default
- UUID primary keys throughout
- Event sourcing tables: `stored_events`, `snapshots`
- Read models: `games`, `game_players`, `game_matches`, `game_standings`, etc.

## Testing

Tests are in `tests/` with standard PHPUnit structure. Run specific tests with `--filter`:

```bash
php artisan test --filter=MatchSimulatorTest
```

## Configuration

Match simulation can be tuned without code changes via `config/match_simulation.php`:
- Base goals, home advantage, strength multipliers
- Performance variance (randomness)
- Event probabilities (cards, injuries)

Clear cache after changes: `php artisan config:clear`

## Internationalization (i18n)

The application uses Spanish as the default language. All user-facing strings must be translatable.

### Translation Files

```
lang/es/
├── app.php        # General UI (buttons, labels, navigation)
├── game.php       # Game-specific terms (season, matchday, etc.)
├── squad.php      # Squad/player related
├── transfers.php  # Transfers, scouting, contracts
├── finances.php   # Financial terms
├── season.php     # Season end, awards, promotions
└── messages.php   # Flash messages (success, error, info)
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

### Adding New Strings

1. Add the key and Spanish translation to the appropriate file in `lang/es/`
2. Use the key in your blade template or PHP code
3. Test that the translation displays correctly

## Code Quality

### No Dead Code

- **Never leave dead code:** Remove functions, methods, and classes that are not called from anywhere
- **Clean up after refactoring:** When refactoring, ensure you remove any code that becomes unused
- **Don't create unused functions:** Only write functions that are actually called by other code
- **Remove commented-out code:** Don't leave commented-out code blocks in the codebase
