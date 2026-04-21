# Player Data Model

How player records are organized across tables, and what belongs where. This is the foundation that [Player Development](player-development.md), [Transfer Market](transfer-market.md), and [Matchday Advancement](matchday-advancement.md) all build on.

## Four tables

Each player is represented by a combination of four records:

| Table | Scope | What it holds |
|-------|-------|---------------|
| `players` | Global (one row per real-world player) | Reference identity: name, date of birth, nationality, baseline ability, height, foot |
| `game_player_templates` | Per-season, shared across games | Per-season starting state: team, position, contract, wage, ability, potential, tier |
| `game_players` | Per-game | Per-game mutable state: current team, contract, wage, ability, market value, `retiring_at_season`, `number` |
| `game_player_match_state` | Per-game satellite | Hot-write matchday state: fitness, morale, injuries, appearances, goals, assists, cards, GK stats |

**Why the split?** Templates let every new game bootstrap from an immutable per-season snapshot without recomputing attributes. The `game_players` row is where per-game divergence lives (contracts renewed, transfers completed, ability developed). The satellite table isolates the columns that get UPDATEd on every matchday from the cold columns that don't — keeping matchday writes narrow and reducing row-level lock contention.

## Active scope vs pool scope

Not every `game_player` participates in simulation. Each row belongs to one of two scopes:

- **Active scope** — players on teams that play real matches in this game: La Liga, Segunda, Copa del Rey participants, and European opponents for seasons the user qualifies for. These players accumulate stats, suffer injuries, develop based on playing time, and drive the matchday loop.
- **Pool scope** — players on foreign-league teams (Premier League, Bundesliga, etc.) that exist solely for the [Transfer Market](transfer-market.md)'s Explore/scouting feature. Foreign-league matches are never simulated. Pool players age, drift in ability (slight decline/stagnation from zero appearances), and can retire — but they never appear in lineups, never accumulate match events, and never trigger contract renewals or AI-to-AI transfers within their pool team.

Scope is derived from `competition_entries` — a team is active if it has an entry in any of the game's competitions. The set is not flagged on `game_players` directly; services compute it on demand.

A pool player **transitions to active scope** at two well-defined events:

1. **Transfer completion** — user signs a pool player (or an AI-to-user transfer places them in active scope).
2. **European draw** — a pool team is drawn as a UCL/UEL/UECL opponent for the upcoming season.

The reverse transition (active → pool) can happen on sale abroad or relegation, but is rare in practice.

## Game creation: cloning templates

When a new game is created, `SetupNewGame::initializeGamePlayersFromTemplates()` clones **every non-national-team row** from `game_player_templates` into `game_players` for the new `game_id`. This means every game starts with the full player universe — active-scope teams and pool teams alike — copied row-by-row.

This clone is what isolates games from each other (each career's Haaland develops independently), and it's why per-game row counts scale with the size of the template table rather than the user's actual footprint.

The companion call, `GamePlayerMatchState::createForPlayers()`, currently creates a satellite row for every cloned `game_player` — including pool players (see invariant below).

## The `game_player_match_state` eager-materialization invariant

> Every `game_player` has exactly one `game_player_match_state` row, created in the same transaction that inserts the parent.

This invariant is enforced at four write sites:

| Site | When |
|------|------|
| `SetupNewGame::initializeGamePlayersFromTemplates` | New game creation |
| `PlayerGeneratorService::create` / `createBulk` | Youth intakes, replenishment, retirement replacements |
| `SetupTournamentGame` | Tournament mode setup |
| `SaveSquadSelection` | User squad creation |

Plus a one-shot `app:backfill-match-states` artisan command for pre-existing games.

### Why eager, not lazy

Lazy materialization was tried and reverted. The previous implementation (`GamePlayerMatchState::ensureExistForGamePlayers`) did a bulk `INSERT ... SELECT ... ORDER BY gp.id ON CONFLICT (game_player_id) DO NOTHING` inside `MatchdayOrchestrator::processBatch` — i.e., inside the matchday `DB::transaction` that also holds a `Game::lockForUpdate()` and performs many subsequent writes.

Even with `ORDER BY` for deterministic lock acquisition, production hit ~132 deadlocks/day (`40P01`). The deadlock surface came from three stacked conditions:

1. `INSERT ... ON CONFLICT` still acquires heap, PK-index, and FK-parent locks on every candidate row — not just rows actually inserted.
2. That bulk write sat inside a long, lock-heavy matchday transaction.
3. Many users run matchdays concurrently, contending on the shared table.

Moving to eager materialization dropped the deadlock rate to ~2/day (residual races on tiny INSERTs with no actual work). The call site was removed in `#849`.

### Cost of the invariant

The guarantee that every `game_player` has a satellite row means pool players carry `game_player_match_state` rows they never read or write. With roughly 6,000 `game_players` per game (active + pool) and typically only ~500 active-scope, ~90% of satellite rows are pure invariant tax. On a database with thousands of games this dominates the storage footprint for these two tables.

The original migration (`2026_04_11_000001_create_game_player_match_state_table.php`) described the target as "only populated for active players." The current eager invariant is stricter than the original design, adopted as the safer alternative to the deadlocking lazy path.

## Read paths

`GamePlayer::matchStateValue()` is the single read funnel. It looks up each satellite-backed attribute in three steps:

1. In-memory override (dirty attribute)
2. Eager-loaded `matchState` relation (authoritative when loaded)
3. Lazy-load, or fall back to `GamePlayerMatchState::DEFAULTS`

The default fallback makes reads safe even when a satellite row is absent — callers get the same defaults a freshly-created row would hold. Hot paths (matchday, squad, lineup, dashboard) eager-load `matchState` via `with('matchState')` to avoid N+1.

## Write paths

All satellite writes go through centralized bulk methods on `GamePlayerMatchState`:

| Method | Purpose |
|--------|---------|
| `bulkIncrementStats` | Goals, assists, cards, own goals, goalkeeper stats |
| `bulkIncrementAppearances` / `bulkDecrementAppearances` | Appearance counters (decrement used in resimulation) |
| `bulkSetValues` | Fitness, morale, arbitrary absolute updates |
| `bulkResetForGame` | Season-end stat reset (priority 65 in closing pipeline) |
| `bulkSetInjuries` / `setInjury` / `clearInjury` | Injury fields |

Callers never reference table name or column list directly. All bulk updates use single `CASE WHEN` SQL statements for atomicity and performance.

## Per-game isolation

Every mutable player record is scoped by `game_id`: `game_players.game_id`, `game_player_match_state.game_id`, all match events, suspensions, and transfers. Each user career is a fully independent simulation. Two users playing the same season see the same templates but entirely different outcomes — different retirements, different development, different youth intakes.

This isolation is a strength (no cross-career interference, trivial per-game deletion) and the root of the storage scaling concern: every new country league or new season of template data multiplies across every active game.

## Key Files

| File | Purpose |
|------|---------|
| `app/Models/Player.php` | Reference identity record |
| `app/Models/GamePlayer.php` | Per-game player state; satellite accessor delegates |
| `app/Models/GamePlayerMatchState.php` | Satellite model + centralized bulk write API |
| `app/Models/GamePlayerTemplate.php` | Per-season template snapshot |
| `app/Modules/Season/Jobs/SetupNewGame.php` | Template → `game_players` clone on game creation |
| `app/Modules/Squad/Services/PlayerGeneratorService.php` | Generates new players (youth, replenishment, retirement replacements) |
| `app/Modules/Season/Processors/StatsResetProcessor.php` | Season-end satellite reset |
| `app/Console/Commands/BackfillMatchStates.php` | One-shot backfill for pre-invariant games |
