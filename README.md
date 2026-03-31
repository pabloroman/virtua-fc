# VirtuaFC

<p align="center">
  <b>If you enjoy the game, consider supporting the project:</b><br><br>
  <a href="https://www.paypal.com/donate/?hosted_button_id=CNC9ARRMU3X6E"><img src="https://img.shields.io/badge/PayPal-Donate-00457C?style=for-the-badge&logo=paypal&logoColor=white" alt="PayPal Donate"></a>
</p>

A football manager simulation game built with Laravel 12, Tailwind CSS, and Alpine.js.

## Features

### Competitions
- Manage a football team in the Spanish league system (La Liga, Segunda Division)
- Compete in the Copa del Rey knockout cup and the Supercopa de Espana
- Qualify for European competitions: Champions League, Europa League, and Conference League
- League standings, cup brackets, and Swiss-format group stages

### Match Simulation
- Realistic match engine with Poisson-based goal distribution
- Player events: goals, assists, yellow/red cards, injuries, substitutions
- 8 formation tactics (4-4-2, 4-3-3, 4-2-3-1, 3-4-3, 3-5-2, 4-1-4-1, 5-3-2, 5-4-1)
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
- End-of-season pipeline with 21 ordered processors
- Season archiving for historical records

## Prerequisites

- PHP 8.4 or higher
- Composer
- Node.js and npm
- Docker (PostgreSQL and Redis are provided via Docker Compose)

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

5. **Start Docker services**

   ```bash
   docker compose up -d
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

# Queue worker (required for background jobs)
php artisan queue:listen --tries=1

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

### Modular Monolith

The codebase follows a modular monolith pattern with domain logic organized into 9 modules under `app/Modules/`:

| Module | Purpose |
|--------|---------|
| **Match** | Match simulation engine |
| **Lineup** | Tactical layer (formations, substitutions) |
| **Squad** | Player management and development |
| **Transfer** | Market operations, contracts, loans, scouting |
| **Competition** | Structure, configuration, and handlers |
| **Finance** | Economic model and budget projections |
| **Season** | Lifecycle orchestration and end-of-season pipeline |
| **Notification** | In-game messaging |
| **Academy** | Youth development |

The HTTP layer uses invokable single-action classes: **Actions** (`App\Http\Actions\*`) for form submissions and **Views** (`App\Http\Views\*`) for data preparation.

### Competition Handlers

Different competition types use a pluggable handler system implementing `CompetitionHandler`:

- `LeagueHandler` — standard league with standings
- `KnockoutCupHandler` — Copa del Rey bracket/draws
- `LeagueWithPlayoffHandler` — league with playoff rounds
- `SwissFormatHandler` — Champions League Swiss-system format

### Match Simulation

The match simulator (`App\Modules\Match\Services\MatchSimulator`) uses configurable parameters:

- Base expected goals with home advantage
- Team strength calculation from lineup players
- Formation modifiers (attack/defense balance)
- Mentality modifiers (risk vs. safety)
- Player performance variance (form on the day)

Parameters can be tuned in `config/match_simulation.php` without code changes.

### Season Pipelines

Season transitions use two pipelines: `SeasonClosingPipeline` (16 processors for closing the old season) and `SeasonSetupPipeline` (7 processors for setting up the new season). All processors implement `SeasonProcessor`. The setup pipeline is shared between season transitions and new game creation.

## Game Design Documentation

Detailed documentation on game systems and design decisions:

- **[Game Systems Overview](docs/game-systems/README.md)** — Index of all game system documentation
- **[Player Abilities](docs/game-systems/player-abilities.md)** — How abilities are derived from market value with age adjustments
- **[Player Potential](docs/game-systems/player-potential.md)** — How potential is generated and influences development
- **[Player Development](docs/game-systems/player-development.md)** — How players grow and decline over seasons
- **[Market Value Dynamics](docs/game-systems/market-value-dynamics.md)** — How market value evolves with ability and age
- **[Match Simulation](docs/game-systems/match-simulation.md)** — xG formula, energy system, formations, mentality, events
- **[Injury System](docs/game-systems/injury-system.md)** — Injury probability, durability, medical tiers, recovery
- **[Season Lifecycle](docs/game-systems/season-lifecycle.md)** — Season flow, matchday progression, end-of-season pipeline
- **[Club Economy System](docs/game-systems/club-economy-system.md)** — Budget allocation, revenue sources, investment tiers
- **[Transfer Market](docs/game-systems/transfer-market.md)** — Scouting, buying, selling, loans, contracts
- **[Youth Academy](docs/game-systems/academy-redesign.md)** — Phased stat reveals, development, evaluations

## Data Structure

Reference data is stored in JSON files under `data/2025/`:

```
data/2025/
├── ESP1/          # La Liga
├── ESP2/          # Segunda Division
├── ESPCUP/        # Copa del Rey
├── ESPSUP/        # Supercopa de Espana
├── UCL/           # Champions League
├── UEL/           # Europa League
├── EUR/           # European club data by country
├── DEU1/          # Bundesliga
├── ENG1/          # Premier League
├── FRA1/          # Ligue 1
├── ITA1/          # Serie A
└── WC2026/        # World Cup 2026
```

## License

Copyright (c) 2026 Pablo Roman. All rights reserved.

This source code is made available for viewing and educational purposes only. See [LICENSE](LICENSE) for details.
