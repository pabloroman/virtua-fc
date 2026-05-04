# Migration plane convention

Migrations are organized by which database plane they target. See
`CLAUDE.md` → "Control plane / tenant plane" for the boundary rules.

## Where to put a new migration

| Migration target | Directory |
|------------------|-----------|
| Tables on the **control** plane (cross-tenant data: `users`, `teams`, `competitions`, `manager_stats`, `manager_trophies`, `tournament_summaries`, `game_player_templates`, onboarding tables, etc.) | `database/migrations/control/` |
| Tables on the **tenant** plane (per-game state: `games`, `game_matches`, `game_players`, `game_standings`, transfers, lineups, etc.) | `database/migrations/tenant/` |
| **Historical** migrations created before the split landed | flat `database/migrations/` |

## How the loader picks the right connection

`AppServiceProvider::register` registers both subdirs via `loadMigrationsFrom`. Migrations themselves declare the connection they target via `Schema::connection('pgsql_control')->table(...)` or by relying on the default `pgsql` (tenant) connection.

For migrations that need to mutate both planes (rare — the `is_reserve_squad` denormalization is one example), file under whichever plane is the primary mutation; use explicit `Schema::connection('pgsql_control')->...` for the other.

## What about cross-plane operations?

A migration in `tenant/` can read from `pgsql_control` (e.g., to backfill a denormalized column from a reference table), but **must not** declare a database-level FK that points across planes. See `2026_05_08_000001_drop_cross_plane_fk_constraints.php` for the canonical example of why: Postgres cannot enforce an FK across two physical instances, so any cross-plane FK becomes a hard error the day the planes split.
