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
