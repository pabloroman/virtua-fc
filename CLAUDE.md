# CLAUDE.md

## Project Overview

VirtuaFC is a football manager simulation game built with Laravel 12. Players manage Spanish football teams (La Liga/Segunda División) through seasons, handling squad selection, transfers, and competitions including the Copa del Rey and European competitions (Champions League, Europa League, Conference League).

The frontend uses Blade templates with Tailwind CSS and Alpine.js. The app defaults to Spanish (`APP_LOCALE=es`).

## Development Commands

```bash
composer dev                                    # Run all services (server, queue, vite, logs)
php artisan test                                # Run tests
php artisan test --filter=TestClassName          # Run a single test
php artisan app:seed-reference-data             # Seed reference data (--fresh to reset)
php artisan app:simulate-match                  # Simulate a match (debugging)
php artisan app:simulate-season                 # Simulate a full season
php artisan config:clear                        # Clear config cache after changes
```

The queue worker must be running for background jobs. `composer dev` handles this via `php artisan queue:listen --tries=1`.

## Architecture

### HTTP Layer

Uses invokable single-action classes instead of controllers:

- **Actions:** `App\Http\Actions\*` — form submissions and game commands
- **Views:** `App\Http\Views\*` — prepare data for Blade templates
- **Auth:** Laravel Breeze controllers in `App\Http\Controllers\Auth\`

**Views and Actions must stay thin.** They only orchestrate: validate input, call a service, return a response. Business logic, database queries, and data transformations belong in service classes (`app/Modules/*/Services/`). Never put domain logic in a View or Action.

### Modular Monolith

Domain logic is organized into modules under `app/Modules/`, each with services, contracts, DTOs, and events:

| Module | Purpose | Key services |
|--------|---------|-------------|
| **Match** | Match simulation engine | `MatchSimulator`, `MatchdayService`, `CupTieResolver`, handlers |
| **Lineup** | Tactical layer | `LineupService`, `SubstitutionService`, `FormationRecommender` |
| **Player** | Player lifecycle | `PlayerDevelopmentService`, `PlayerConditionService`, `PlayerValuationService`, `InjuryService`, `PlayerRetirementService` |
| **Squad** | Squad composition | `PlayerGeneratorService`, `EligibilityService` |
| **Transfer** | Market operations | `TransferService`, `ContractService`, `LoanService`, `ScoutingService` |
| **Competition** | Structure & config | `CountryConfig`, `StandingsCalculator`, `CupDrawService` |
| **Finance** | Economic model | `BudgetProjectionService`, `SeasonSimulationService` |
| **Season** | Lifecycle orchestration | `SeasonClosingPipeline`, `SeasonSetupPipeline`, `GameCreationService` |
| **Manager** | Profile, trophies & leaderboard | `ManagerProfileService`, `LeaderboardService` |
| **Notification** | In-game messaging | `NotificationService` |
| **Academy** | Youth development | `YouthAcademyService` |
| **Report** | End-of-season/tournament reports & awards | `SeasonSummaryService`, `CompetitionSummaryService`, `AwardService` |

**Dependency direction:** Season (orchestrator) → Match, Transfer, Finance → Player, Squad, Competition → Notification (leaf). No circular dependencies.

Models stay in `app/Models/` (shared). The HTTP layer stays in `app/Http/` as thin orchestrators.

### Competition Handlers

Handlers implement `App\Modules\Competition\Contracts\CompetitionHandler`, resolved via `CompetitionHandlerResolver` based on `handler_type`:

- `LeagueHandler`, `KnockoutCupHandler`, `LeagueWithPlayoffHandler`, `SwissFormatHandler`

Competition-specific config (revenue rates, etc.) lives in `App\Modules\Competition\Configs\*` (e.g., `LaLigaConfig`, `ChampionsLeagueConfig`).

### Season Pipelines

Two pipelines with processors implementing `SeasonProcessor` (see `SeasonClosingPipeline` and `SeasonSetupPipeline` for the full ordered list):

- **SeasonClosingPipeline** — closes the old season (loans, contracts, development, promotions, UEFA qualification, etc.)
- **SeasonSetupPipeline** — sets up the new season (fixtures, standings, budgets, cups, etc.)

New processors can be added without modifying existing code.

### Financial Model

Uses projection-based budgeting (not running balance): `GameFinances` (projections), `GameInvestment` (allocation), `FinancialTransaction` (reconciliation). Revenue rates are defined per competition config, not on `ClubProfile`. Commercial revenue grows via position-based multipliers in `config/finances.php`.

## Critical Constraints

These are non-obvious rules that prevent bugs. Read carefully.

### Database

- **PostgreSQL in production**, SQLite in dev. **All raw SQL must be PostgreSQL-compatible.** Prefer Eloquent query builder. When raw SQL is unavoidable, branch on `getConnection()->getDriverName()` for `pgsql` vs `sqlite`.
- **UUID primary keys** throughout.
- **No wall-clock timestamps on game models.** Time follows the game-universe calendar (`current_date` on `Game`). Models should set `public $timestamps = false` and omit `$table->timestamps()` from migrations (except `users`).
- **`currentFinances` and `currentInvestment`** relationships use `$this->season` internally. Always use lazy loading — never eager load with `with()`.

### Internationalization

**Both `lang/es/` and `lang/en/` must be updated** for every new translation key. All user-facing strings use `__()` in Blade and PHP.

| Category | Key format | Example |
|----------|-----------|---------|
| Buttons/actions | `app.*` | `app.save`, `app.confirm` |
| Game terms | `game.*` | `game.season`, `game.matchday` |
| Squad labels | `squad.*` | `squad.goalkeepers` |
| Transfer terms | `transfers.*` | `transfers.bid_rejected` |
| Finance terms | `finances.*` | `finances.transfer_budget` |
| Flash messages | `messages.*` | `messages.player_listed` |
| Season end | `season.*` | `season.champion` |
| Cup terms | `cup.*` | `cup.round` |
| Notifications | `notifications.*` | `notifications.transfer_complete` |

### Alpine.js: PHP Values in `x-data`

**Never use raw Blade interpolation (`'{{ }}'`) to pass PHP values into Alpine `x-data` expressions.** Use `@js()` instead. Raw interpolation breaks if the value contains quotes, newlines, or special characters, silently corrupting the entire Alpine component.

```blade
{{-- Bad: breaks on quotes/newlines in user input --}}
x-data="{ bio: '{{ $user->bio }}' }"

{{-- Good: @js() handles all escaping --}}
x-data="{ bio: @js($user->bio) }"
```

### UI: Design System

The design system (`resources/views/design-system/`) is the source of truth. Before building any UI: check `resources/views/design-system/sections/` for patterns, then `resources/views/components/` for existing components. Reuse exactly as defined. Never invent alternative styles for elements that already have a design system definition. New patterns go in the design system first, then get implemented as components.

**Create new components liberally.** If a UI element has a reasonable chance of being reused (badges, stat rows, player cards, status indicators, etc.), extract it into `resources/views/components/` and add it to the design system. Prefer many small, reusable components over repeated inline markup.

### UI: Mobile Responsiveness

Every feature must work at 375px (phone) and 768px (tablet). Mobile-first Tailwind: base styles for mobile, `md:`/`lg:` for larger screens.

**Never:**
- Use bare `grid-cols-N` (N > 1) — always start with `grid-cols-1 md:grid-cols-N`
- Use bare `col-span-N` — always prefix with `md:` or `lg:`
- Set fixed widths that overflow on 375px
- Hide critical game actions on mobile
- Use hover-only interactions — `:hover` must also work via tap/click

**Data tables:** Wrap in `overflow-x-auto`, hide non-essential columns with `hidden md:table-cell`, ensure 44px touch targets.

**Navigation:** Slide-out drawer on mobile (`game-header.blade.php`). New nav items must be added to **both** desktop nav and mobile drawer.

**Font scaling:** Custom root font-size in `resources/css/app.css` (14px mobile, ~20px desktop). Use Tailwind `text-*` utilities, never fixed `px` for font sizes.

### UI: Dark Mode & Light Mode

Every feature must look correct in both themes. Dark mode is `:root` default; light mode activates via `.light` class. CSS custom properties in `resources/css/app.css`.

| Token | Dark | Light | Usage |
|-------|------|-------|-------|
| `surface-900` | `#0b1120` | `#ffffff` | Page background |
| `surface-800` | `#0f172a` | `#f8fafc` | Card backgrounds |
| `surface-700` | `#1e293b` | `#f1f5f9` | Elevated elements, hover states |
| `surface-600` | `#334155` | `#e2e8f0` | Borders, dividers |

**Rules:**
- Always use semantic tokens (`bg-surface-*`, `text-text-*`, `border-border-*`, `bg-accent-*`). Never raw Tailwind colors (`bg-slate-700`, `text-gray-300`) or absolute colors (`text-white`, `bg-black`).
- **Never use `bg-surface-800`** for interactive elements on the page background — insufficient contrast in light mode. Use `bg-surface-700` or add a visible border.
- Never use `bg-white/5` or similar opacity backgrounds as the only visual differentiator — results differ dramatically between themes.
- Accent colors (`bg-accent-blue/10`, `bg-accent-green/15`) work in both themes for active/selected states.

## Game Systems Documentation

`docs/game-systems/` documents game mechanics at a conceptual level (index: `docs/game-systems/README.md`). Update docs only for new systems, structural changes, new processors, or renamed services. Don't update for config tweaks, formula adjustments, or bug fixes.

## Backend Performance

**Prevent N+1 queries.** When building new features or modifying queries, always consider whether related models need eager loading (`with()`). Adding a loop that accesses a relationship, a new Blade partial that touches `->player`, or a collection map over a relation are all common sources of N+1 problems. Use eager loading proactively — don't wait for it to become a performance issue. (Exception: `currentFinances`/`currentInvestment` must be lazy-loaded — see Database constraints above.)

## Code Quality

Never leave dead code, commented-out code, or unused functions. Clean up after refactoring.
