# VirtuaFC

A football manager simulation game built with Laravel and Event Sourcing.

## Features

### Competitions
- Manage a football team in the Spanish league system (La Liga, Segunda Division)
- Compete in the Copa del Rey knockout cup competition
- League standings and cup brackets

### Match Simulation
- Realistic match engine with Poisson-based goal distribution
- Player events: goals, assists, yellow/red cards, injuries
- Formation tactics (4-4-2, 4-3-3, 3-5-2, etc.) affecting attack/defense balance
- Team mentality (Defensive/Balanced/Attacking) for tactical adjustments
- Configurable simulation parameters via `config/match_simulation.php`

### Squad Management
- Player squads with technical and physical attributes
- Fitness system: players lose fitness when playing, recover during rest
- Morale system: affected by match results and individual performance
- Lineup selection with position compatibility indicators

### Season Progression
- Player development: young players improve, older players decline
- End of season summary with awards (top scorer, most assists, best goalkeeper)
- Season archiving for historical records
- New season initialization with stat resets and fixture generation

## Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js and npm
- SQLite (default) or MySQL/PostgreSQL

## Installation

1. **Clone the repository**

   ```bash
   git clone git@github.com:pabloroman/virtua-fc.git
   cd virtua-fc
   ```

2. **Install PHP dependencies**

   ```bash
   composer install
   ```

3. **Install JavaScript dependencies**

   ```bash
   npm install
   ```

4. **Configure environment**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Create the database**

   For SQLite (default):
   ```bash
   touch database/database.sqlite
   ```

6. **Run migrations**

   ```bash
   php artisan migrate
   ```

7. **Seed reference data**

   This populates teams, players, competitions, and fixtures:
   ```bash
   php artisan app:seed-reference-data
   ```

   To reset and re-seed all data:
   ```bash
   php artisan app:seed-reference-data --fresh
   ```

## Default User

After seeding, you can log in with:

- **Email:** test@test.com
- **Password:** password

## Running the Application

### Development Server

Run all services concurrently (web server, queue worker, Vite, logs):

```bash
composer dev
```

Or run services individually:

```bash
# Web server
php artisan serve

# Queue worker (required for event sourcing)
php artisan queue:listen

# Vite for frontend assets
npm run dev
```

### Production Build

```bash
npm run build
```

## Running Tests

```bash
php artisan test
```

Or with PHPUnit directly:

```bash
./vendor/bin/phpunit
```

## Architecture

### Event Sourcing

The game uses [Spatie Laravel Event Sourcing](https://spatie.be/docs/laravel-event-sourcing) for match results and game state. Key components:

- **Aggregates:** `App\Game\Game` - handles game commands and emits events
- **Events:** `App\Game\Events\*` - recorded when matches are played, cup ties resolved, etc.
- **Projectors:** `App\Game\GameProjector` - builds read models from events

### Competition Handlers

Different competition types (leagues, cups) use a pluggable handler system:

- `App\Game\Contracts\CompetitionHandler` - interface for competition behavior
- `App\Game\Handlers\LeagueHandler` - groups matches by round, updates standings
- `App\Game\Handlers\KnockoutCupHandler` - groups by date, conducts draws, resolves ties
- `App\Game\Services\CompetitionHandlerResolver` - resolves handlers by competition type

This architecture allows easy addition of new competition types (European cups, playoffs) without modifying core game logic.

### Match Simulation

The match simulator (`App\Game\Services\MatchSimulator`) uses configurable parameters:

- Base expected goals with home advantage
- Team strength calculation from lineup players
- Formation modifiers (attack/defense balance)
- Mentality modifiers (risk vs. safety)
- Player performance variance (form on the day)

Parameters can be tuned in `config/match_simulation.php` without code changes.

### Season End Pipeline

End of season processing uses a pluggable processor system:

- `App\Game\Contracts\SeasonEndProcessor` - interface for season transition tasks
- `App\Game\Processors\SeasonArchiveProcessor` - archives season data
- `App\Game\Processors\PlayerDevelopmentProcessor` - applies player growth/decline
- `App\Game\Processors\StatsResetProcessor` - resets season statistics
- `App\Game\Processors\FixtureGenerationProcessor` - creates next season fixtures
- `App\Game\Services\SeasonEndPipeline` - orchestrates processors in priority order

New processors can be added without modifying existing code.

## Data Structure

Reference data is stored in JSON files under `data/`:

```
data/
├── ESP1/2024/           # La Liga
│   ├── competition.json
│   ├── teams.json
│   ├── fixtures.json
│   └── players/
├── ESP2/2024/           # Segunda Division
└── transfermarkt/ESPCUP/2024/  # Copa del Rey
```

## License

This project is private.
