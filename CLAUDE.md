# CLAUDE.md

## Project Overview

VirtuaFC is a football manager simulation game built with Laravel 12. Players manage Spanish football teams (La Liga/Segunda División) through seasons, handling squad selection, transfers, and competitions including the Copa del Rey and European competitions (Champions League, Europa League, Conference League).

The frontend uses Blade templates with Tailwind CSS and Alpine.js. The app defaults to Spanish (`APP_LOCALE=es`).

**Stack versions:** PHP 8.5, Laravel 12, PHPUnit 11. Tailwind CSS 4.x (via `@tailwindcss/vite`), Alpine.js 3.x, Vite 7.x, Vitest 4.x. No ESLint/Prettier — only `.editorconfig` (4-space indent, LF, UTF-8) and Laravel Pint for PHP.

## Development Commands

```bash
composer dev                                    # Run all services (server, queue, vite, logs)
php artisan test                                # Run tests
php artisan test --filter=TestClassName          # Run a single test
php artisan app:seed-reference-data             # Seed reference data (--fresh to reset)
php artisan app:simulate-match                  # Simulate a match (debugging)
php artisan app:simulate-season                 # Simulate a full season
php artisan config:clear                        # Clear config cache after changes
./vendor/bin/phpstan analyse                    # Larastan static analysis (level 1)
```

The queue worker must be running for background jobs. `composer dev` handles this via `php artisan queue:listen --tries=1`.

**Do not run tests or static analysis automatically after making changes.** Both run in CI after pushing to a branch. Only run them locally when explicitly asked.

**Game-state debugging commands** (full list in `app/Console/Commands/`):

```bash
php artisan app:diagnose-stuck-game {game}      # Investigate a stalled game
php artisan app:cleanup-games                   # Remove orphaned/abandoned games
php artisan app:refresh-player-templates        # Reseed player biography source
php artisan app:unstick-season-transition       # Unblock a stuck season transition
```

## Testing

- **PHPUnit 11** (no Pest). Tests live in `tests/Unit/` and `tests/Feature/`.
- The base `tests/TestCase.php` sets `protected $connectionsToTransact = ['pgsql']` and calls `$this->withoutVite()` in `setUp()`.
- **Factories use fluent helpers** — prefer them over manual wiring. Examples: `Game::factory()->forTeam($team)->create()`, `GamePlayer::factory()->forTeam($team)->create()`, `Game::factory()->inCompetition($id)->create()`.
- Parallel runs via `paratest` are available (`php artisan test --parallel`) but not the default.
- Static analysis: Larastan at level 1 (`./vendor/bin/phpstan analyse`). Strict where it matters; permissive elsewhere.

## Architecture

### HTTP Layer

Uses invokable single-action classes instead of controllers:

- **Actions:** `App\Http\Actions\*` — form submissions and game commands
- **Views:** `App\Http\Views\*` — prepare data for Blade templates
- **Auth:** Laravel Breeze controllers in `App\Http\Controllers\Auth\`

**Views and Actions must stay thin.** They only orchestrate: validate input, call a service, return a response. Business logic, database queries, and data transformations belong in service classes (`app/Modules/*/Services/`). Never put domain logic in a View or Action.

**Route wiring** (`routes/web.php`): routes bind invokable Actions/Views directly — `Route::get('/manager/{username}', ShowManagerProfile::class)`. No controller classes for game flows. Route names use dot notation (`leaderboard.teams`, `tournament-summary.show`). Game-scoped routes go behind the `game.owner` middleware (defined in `bootstrap/app.php`, implemented by `App\Http\Middleware\EnsureGameOwnership`). Any new route that operates on a specific game must be inside that middleware group.

### Modular Monolith

Domain logic is organized into modules under `app/Modules/`, each with services, contracts, DTOs, and events. Conceptual mechanics for many of these modules are documented in `docs/game-systems/` (index: `docs/game-systems/README.md`) — cross-references are noted below.

| Module | Purpose | Key services | Deep dives in `docs/game-systems/` |
|--------|---------|-------------|------------------------------------|
| **Match** | Match simulation engine | `MatchSimulator`, `MatchdayService`, `CupTieResolver`, handlers | `match-simulation.md`, `matchday-advancement.md` |
| **Lineup** | Tactical layer | `LineupService`, `SubstitutionService`, `FormationRecommender` | — |
| **Player** | Player lifecycle | `PlayerDevelopmentService`, `PlayerConditionService`, `PlayerValuationService`, `InjuryService`, `PlayerRetirementService` | `player-development.md`, `player-abilities.md`, `player-potential.md`, `injury-system.md` |
| **Squad** | Squad composition | `PlayerGeneratorService`, `EligibilityService` | `squad-page-redesign.md` |
| **ReserveTeam** | Reserve / B-team and U23 cascades | `ReserveTeamService` | — |
| **Transfer** | Market operations | `TransferService`, `ContractService`, `LoanService`, `ScoutingService` | `transfer-market.md`, `market-value-dynamics.md` |
| **Competition** | Structure & config | `CountryConfig`, `StandingsCalculator`, `CupDrawService` | — |
| **Finance** | Economic model | `BudgetProjectionService`, `SeasonSimulationService` | `club-economy-system.md` |
| **Stadium** | Capacity, attendance & upgrades | `StadiumCapacityResolver`, `StadiumUpgradeService`, `MatchAttendanceService`, `FanLoyaltyService`, `SeasonTicketPricingService`, `DemandCurveService` | `stadium-and-facilities.md` |
| **Reputation** | Club & competition reputation | `ReputationSummaryService` | `reputation-system.md` |
| **Season** | Lifecycle orchestration | `SeasonClosingPipeline`, `SeasonSetupPipeline`, `GameCreationService` | `season-lifecycle.md` |
| **Manager** | Profile, trophies & leaderboard | `ManagerProfileService`, `LeaderboardService` | — |
| **Notification** | In-game messaging | `NotificationService` | — |
| **Academy** | Youth development | `YouthAcademyService` | `academy-redesign.md` |
| **Report** | End-of-season/tournament reports & awards | `SeasonSummaryService`, `CompetitionSummaryService`, `AwardService` | — |
| **Analytics** | Internal usage/engagement analytics | `ActivationFunnelService`, `DashboardStatsService`, `GameStatsService`, `DeviceStatsService` | — |
| **Editor** | Admin tools for reference data (player templates, etc.) | `PlayerTemplateAdminService` | — |

**Dependency direction:** Season (orchestrator) → Match, Transfer, Finance → Player, Squad, ReserveTeam, Competition, Stadium, Reputation → Notification (leaf). No circular dependencies.

Models stay in `app/Models/` (shared). The HTTP layer stays in `app/Http/` as thin orchestrators.

**Events as the cross-module seam.** Events extend Laravel's `Dispatchable` and dispatch **synchronously** by default. Listeners use constructor injection; event payloads are readonly properties. Cross-module communication should go through events — don't reach into another module's services directly when an event already exists. Example: `GameDateAdvanced` is the canonical hook for "the user just played a match."

**DTOs and pipeline metadata.** DTOs live in `app/Modules/*/DTOs/` as plain readonly PHP classes (no Spatie); some implement `JsonSerializable`. The Season pipelines thread a single DTO (`SeasonTransitionData`) through every processor; it carries a `metadata` bag (keys like `META_SWISS_POT_DATA`, `META_UCL_WINNER`, `META_UEL_WINNER`) so processors can publish data for later stages without changing the DTO surface. When adding a new processor that needs to share state downstream, prefer a new metadata key over expanding the DTO's typed properties.

### Competition Handlers

Competition format is implemented in **handler classes in `app/Modules/Match/Handlers/`** (not under the Competition module, despite the name), resolved via `CompetitionHandlerResolver` based on `handler_type`:

- `LeagueHandler`, `KnockoutCupHandler`, `LeagueWithPlayoffHandler`, `SwissFormatHandler`, `GroupStageCupHandler`, `PreSeasonHandler`
- `CupCompetitionHandler` is the shared base class for cup-style handlers.

Competition-specific config (revenue rates, etc.) lives in `App\Modules\Competition\Configs\*` (e.g., `LaLigaConfig`, `ChampionsLeagueConfig`). The handler/config split is intentional: handlers describe *how a competition runs*, configs describe *what a competition pays out and qualifies for*.

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

- **PostgreSQL everywhere** (dev and production via Docker). All raw SQL is PostgreSQL. Prefer Eloquent query builder; no driver branching needed.
- **UUID primary keys** throughout. **All new tables must use a UUID `id` column as the primary key** (`$table->uuid('id')->primary()`), not `$table->id()` (bigint autoincrement). Bigint autoincrement PKs collide on the import side of the beta→prod migration (two beta databases can independently produce the same id), and the fix is always a one-off conversion migration. Models on UUID-keyed tables should `use Illuminate\Database\Eloquent\Concerns\HasUuids`. If the table is written to via bulk `insert()` / `insertOrIgnore()` (which bypass the `HasUuids` `creating` listener), also set a DB-side `DEFAULT gen_random_uuid()` on the column.
- **No wall-clock timestamps on game models.** Time follows the game-universe calendar (`current_date` on `Game`). Models should set `public $timestamps = false` and omit `$table->timestamps()` from migrations (except `users`).
- **`current_date` is forward-looking.** It is updated during match finalization to the `scheduled_date` of the next unplayed match. This means `current_date` always represents the date of the upcoming match the user is about to play, **not** the date of the match just played. The `GameDateAdvanced` event carries `previousDate` (old `current_date` / the match just finalized) and `newDate` (the next match to be played). Listeners that need to act "before the user plays match X" should key on `newDate`, not `previousDate`.
- **No `current_matchday` on Game.** The league matchday number is derived from match data (e.g., `round_number` on `GameMatch`). Use `$game->nextLeagueMatchday` accessor to get the next unplayed league round number.
- **`currentFinances` and `currentInvestment`** relationships use `$this->season` internally. Always use lazy loading — never eager load with `with()`.

### Match Event Ordering

Match-simulation event ordering is non-obvious and is the source of several recurring bug classes (phantom ET goals, half-time event sequencing, substitution timing around red cards). Events have an `EXPECTED_STEP` that controls sequencing within a minute, and half-time / extra-time boundaries have specific rules. Before modifying simulation logic, read `docs/game-systems/match-simulation.md` and look at how existing handlers in `app/Modules/Match/Handlers/` sequence events. When you change ordering, add a test with explicit minute/step assertions — implicit "next event" assumptions silently regress.

### Reserve Team & U23 Cascades

The `ReserveTeam` module (filial / B-team) owns reserve rosters and the call-up / send-down / permanent-promotion lifecycle (`ReserveTeamService::callUpToFirstTeam`, `sendBackToReserve`, `sendDownToReserve`, `autoPromoteOverageReservePlayers`, `permanentlyPromoteCalledUpPlayers`). Always go through `ReserveTeamService` when moving players between a first team and its reserve — it enforces filial-relationship checks and the cascading effects on loans, contracts, and eligibility. Don't reimplement these moves in calling code, and don't bypass them in seeders or migrations.

### Player Age Boundaries

**Never hardcode age values in queries or business logic.** Use constants from `App\Modules\Player\PlayerAge` (e.g., `PlayerAge::MIN_RETIREMENT_OUTFIELD`, `PlayerAge::PRIME_END`). To convert an age constant to a date-of-birth cutoff for database queries, use `PlayerAge::dateOfBirthCutoff($age, $referenceDate)` instead of `$date->subYears($age)`. This keeps age boundaries in a single source of truth.

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

**Data tables:** Wrap in `overflow-x-auto`, hide non-essential columns with `hidden md:table-cell`.

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

Never leave dead code, commented-out code, or unused functions. Clean up after refactoring. **Do not remove explanatory comments** — comments that clarify *why* something works a certain way, describe non-obvious behavior, or provide useful context (e.g., inline examples, domain rationale) must be preserved. Only remove comments that are literally dead code (`// $old = thing()`) or that merely restate what the code already says.
