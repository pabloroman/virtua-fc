# VirtuaFC

<p align="center">
  <b>If you enjoy the game, consider supporting the project:</b><br><br>
  <a href="https://www.paypal.com/donate/?hosted_button_id=CNC9ARRMU3X6E"><img src="https://img.shields.io/badge/PayPal-Donate-00457C?style=for-the-badge&logo=paypal&logoColor=white" alt="PayPal Donate"></a>
</p>

A football manager simulation game built with Laravel 13, Tailwind CSS, and Alpine.js.

## Features

### Competitions
- Manage a football team in the Spanish league system (La Liga, Segunda Division)
- Compete in the Copa del Rey knockout cup and the Supercopa de Espana
- Qualify for European competitions: Champions League, Europa League, and Conference League
- League standings, cup brackets, and Swiss-format group stages

### Match Simulation
- Realistic match engine with Poisson-based goal distribution
- Player events: goals, assists, yellow/red cards, injuries, substitutions
- Formation tactics: 4-4-2, 4-3-3, 4-2-3-1, 3-4-3, 3-5-2, 4-1-4-1, 5-3-2, 5-4-1, 4-1-2-3, 4-3-2-1
- Team mentality (Defensive/Balanced/Attacking) for tactical adjustments
- Advanced pitch positioning with a 9x14 grid for player placement
- Coach assistant with tactical recommendations
- Configurable simulation parameters via `config/match_simulation.php`

### Squad Management
- Player squads with technical and physical attributes
- Fitness system: players lose fitness when playing, recover during rest
- Morale system: affected by match results, playing time, and contract status
- Injury system: realistic injuries from minor strains to season-ending ACL tears
- Hidden durability attribute affects injury proneness
- Lineup selection with position compatibility indicators
- Red card handling with dynamic xG recalculation

### Transfer Market
- Scouting system to discover players across leagues
- Player buying, selling, and loan management
- Contract negotiations and renewals
- Pre-contract offers for expiring players
- List players for sale or loan

### Youth Academy
- Academy tiers with prospect intake each season
- Phased ability reveals (unknown -> visible -> potential revealed)
- Promote, loan out, keep, or dismiss academy players
- Accelerated development for loaned academy players (1.5x)

### Financial System
- Projection-based budgeting with revenue and wage forecasts
- Budget allocation across transfers, infrastructure, and academy
- Competition-specific revenue (matchday, commercial, prize money)
- Financial transactions and season-end reconciliation

### Season Progression
- Player development: young players improve, older players decline
- Promotion and relegation between divisions
- End-of-season pipeline with ordered, independently addable processors
- Season archiving for historical records

## Installation

Two local setups are supported. **Docker is the recommended one** — it pins PHP 8.5 for you, so it works regardless of what PHP your machine has. Docker here is a local development convenience only; production runs on Laravel Forge (PHP 8.5, not containerized).

Clone first, either way:

```bash
git clone git@github.com:pabloroman/virtua-fc.git
cd virtua-fc
```

### Option A: Docker (recommended)

Requires Docker and `make`.

1. **Set up the containers**

   Copies `.env.docker` to `.env`, installs Composer dependencies, and generates an app key:
   ```bash
   make setup
   ```

2. **Start the stack** (app, Vite, Horizon, PostgreSQL, Redis)

   ```bash
   make dev
   ```

3. **Migrate and seed** in another terminal

   ```bash
   make artisan cmd="migrate"
   make artisan cmd="app:seed-reference-data"
   ```

Run any artisan command with `make artisan cmd="..."`, Composer with `make composer cmd="..."`, and npm with `make npm cmd="..."`. `make dev-down` stops everything, `make logs` tails.

### Option B: Local PHP

Requires **PHP 8.5 or higher** (Composer enforces this — 8.4 fails a hard platform check), Composer, and Node.js/npm. Docker still provides PostgreSQL and Redis.

1. **Install dependencies**

   ```bash
   composer install
   npm install
   ```

2. **Configure environment**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Start PostgreSQL and Redis**

   ```bash
   docker compose up -d
   ```

4. **Run migrations**

   ```bash
   php artisan migrate
   ```

5. **Seed reference data**

   This populates teams, players, competitions, and fixtures:
   ```bash
   php artisan app:seed-reference-data
   ```

   To reset and re-seed all data:
   ```bash
   php artisan app:seed-reference-data --fresh
   ```

## Running the Application

### Development Server

On the Docker setup, `make dev` already runs everything. On a local PHP setup, run all services concurrently (web server, queue worker, Vite, logs):

```bash
composer dev
```

Or run services individually:

```bash
# Web server
php artisan serve

# Queue worker (required for background jobs)
php artisan queue:listen --tries=1 --queue=gameplay,setup,mail,cleanup

# Vite for frontend assets
npm run dev
```

The `--queue` list is not optional: game jobs are dispatched to those named queues, and a bare `queue:listen` will silently leave them unprocessed.

### Production Build

```bash
npm run build
```

## Running Tests

```bash
php artisan test              # Full suite
php artisan test --parallel   # Parallel via paratest (what CI runs)
npm test                      # Frontend tests (Vitest)
```

Static analysis with Larastan (level 1):

```bash
./vendor/bin/phpstan analyse
```

CI runs the frontend tests, the parallel PHP suite, and static analysis on every push.

## Architecture

### Modular Monolith

The codebase follows a modular monolith pattern with domain logic organized into modules under `app/Modules/`:

| Module | Purpose |
|--------|---------|
| **Match** | Match simulation engine |
| **Lineup** | Tactical layer (formations, substitutions) |
| **Player** | Player lifecycle: development, condition, valuation, injuries, retirement |
| **Squad** | Squad composition, player generation, eligibility |
| **ReserveTeam** | Reserve / B-team and U23 call-up cascades |
| **Transfer** | Market operations, contracts, loans, scouting |
| **Competition** | Structure and configuration |
| **Finance** | Economic model and budget projections |
| **Stadium** | Capacity, attendance, upgrades, ticketing, naming rights |
| **Reputation** | Club and competition reputation |
| **Season** | Lifecycle orchestration and season pipelines |
| **Manager** | Manager profile, trophies, leaderboard |
| **Notification** | In-game messaging |
| **Academy** | Youth development |
| **Report** | End-of-season reports and awards |
| **Analytics** | Internal usage and engagement analytics |
| **Editor** | Admin tools for reference data |

**Dependency direction:** Season (orchestrator) → Match, Transfer, Finance → Player, Squad, ReserveTeam, Competition, Stadium, Reputation → Notification (leaf). Cross-module communication goes through synchronous events.

The HTTP layer uses invokable single-action classes: **Actions** (`App\Http\Actions\*`) for form submissions and **Views** (`App\Http\Views\*`) for data preparation.

### Competition Handlers

Competition format is implemented by a pluggable handler system in `app/Modules/Match/Handlers/` (not under the Competition module, despite the name), resolved via `CompetitionHandlerResolver`:

- `LeagueHandler` — standard league with standings
- `KnockoutCupHandler` — Copa del Rey bracket/draws
- `LeagueWithPlayoffHandler` — league with playoff rounds
- `SwissFormatHandler` — Champions League Swiss-system format
- `GroupStageCupHandler` — group stage followed by knockout rounds
- `PreSeasonHandler` — pre-season friendlies
- `CupCompetitionHandler` — shared base class for the cup-style handlers

Competition-specific config (revenue rates, qualification) lives separately in `App\Modules\Competition\Configs\*`: handlers describe *how a competition runs*, configs describe *what it pays out and qualifies for*.

### Match Simulation

The match simulator (`App\Modules\Match\Services\MatchSimulator`) uses configurable parameters:

- Base expected goals with home advantage
- Team strength calculation from lineup players
- Formation modifiers (attack/defense balance)
- Mentality modifiers (risk vs. safety)
- Player performance variance (form on the day)

Parameters can be tuned in `config/match_simulation.php` without code changes.

### Season Pipelines

Season transitions use two pipelines: `SeasonClosingPipeline` (closes the old season — loans, contracts, development, promotions, UEFA qualification) and `SeasonSetupPipeline` (sets up the new one — fixtures, standings, budgets, cups). All processors implement `SeasonProcessor` and are ordered by `priority()`; see the `$processors` array in each pipeline class for the authoritative list. New processors can be added without modifying existing code. The setup pipeline is shared between season transitions and new game creation.

## Game Design Documentation

Detailed documentation on game systems and design decisions:

- **[Game Systems Overview](docs/game-systems/README.md)** — Index of all game system documentation
- **[Player Abilities](docs/game-systems/player-abilities.md)** — How abilities are derived from market value with age adjustments
- **[Player Potential](docs/game-systems/player-potential.md)** — How potential is generated and influences development
- **[Player Development](docs/game-systems/player-development.md)** — How players grow and decline over seasons
- **[Market Value Dynamics](docs/game-systems/market-value-dynamics.md)** — How market value evolves with ability and age
- **[Matchday Advancement](docs/game-systems/matchday-advancement.md)** — Core gameplay loop: batch finding, simulation, round generation, deferred finalization
- **[Match Simulation](docs/game-systems/match-simulation.md)** — xG formula, energy system, formations, mentality, events, penalties
- **[Injury System](docs/game-systems/injury-system.md)** — Injury probability, durability, medical tiers, recovery
- **[Season Lifecycle](docs/game-systems/season-lifecycle.md)** — Season flow, matchday progression, end-of-season pipeline
- **[Club Economy System](docs/game-systems/club-economy-system.md)** — Budget allocation, revenue sources, investment tiers, debt
- **[Stadium & Facilities](docs/game-systems/stadium-and-facilities.md)** — Attendance, fan base, ticketing, sponsor portfolio, capital upgrades
- **[Reputation System](docs/game-systems/reputation-system.md)** — Dynamic reputation tiers based on sustained performance
- **[Transfer Market](docs/game-systems/transfer-market.md)** — Scouting, buying, selling, loans, contracts
- **[Release Clauses](docs/game-systems/release-clauses.md)** — Buyout clauses: amount, ES-mandatory rules, user/AI triggers
- **[Youth Academy](docs/game-systems/academy-redesign.md)** — Phased stat reveals, development, evaluations
- **[Squad Page Redesign](docs/game-systems/squad-page-redesign.md)** — UI/UX design reference

## Data Structure

Reference data is stored in JSON files under `data/`, organized by season (`2025/`, `2026/`) plus a shared `players/` directory:

```
data/2025/
├── ESP1/          # La Liga
├── ESP2/          # Segunda Division
├── ESP3A/         # Primera Federacion (Group A)
├── ESP3B/         # Primera Federacion (Group B)
├── ESP3PO/        # Primera Federacion playoffs
├── ESPCUP/        # Copa del Rey
├── ESPSUP/        # Supercopa de Espana
├── UCL/           # Champions League
├── UEL/           # Europa League
├── UECL/          # Conference League
├── UEFASUP/       # UEFA Super Cup
├── EUR/           # European club data by country
├── DEU1/          # Bundesliga
├── ENG1/          # Premier League
├── FRA1/          # Ligue 1
├── ITA1/          # Serie A
├── INT/           # International teams
├── WC2026/        # World Cup 2026
└── raw/           # Unprocessed source data
```

## License

Copyright (c) 2026 Pablo Roman. All rights reserved.

This source code is made available for viewing and educational purposes only. See [LICENSE](LICENSE) for details.
