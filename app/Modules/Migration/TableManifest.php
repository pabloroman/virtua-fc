<?php

namespace App\Modules\Migration;

/**
 * Source of truth for which rows belong to a user during the beta→prod
 * migration. Both the export and import sides read from here so they stay in
 * sync.
 *
 * The list is hand-maintained, not derived from the schema, because:
 *   - it has to encode insert order (FK dependencies) for the import side, and
 *   - we explicitly opt-in tables here so a future migration that adds a new
 *     `game_id`-keyed table also forces a code change in this file.
 *
 * If you add a new table that holds per-game state, add it here and pick the
 * right "scope" — single-game (game_id), match-scoped (game_match_id), or
 * player-scoped (game_player_id). The order within each scope group matters
 * only insofar as FKs require it; see UserImporter for the actual insert pass.
 */
final class TableManifest
{
    /**
     * Control-plane tables that hold per-user rows to be copied during
     * migration.
     *
     * Two columns per table, because the export-side scope and the
     * import-side uniqueness aren't always the same:
     *   - `user_key`  — column on this table that links a row to the user.
     *                   Used by the exporter to pick which rows to ship.
     *   - `row_key`   — column that uniquely identifies a single row.
     *                   Used by the importer as the upsert lookup.
     *
     * For `users` both happen to be `id`. For `manager_stats` they differ:
     * the table holds one row per career game (unique on `game_id` since
     * the 2026_03_17_233425 schema change dropped the original `user_id`
     * unique), so the exporter filters by `user_id` but the importer must
     * upsert per-row by `id` — keying the upsert on `user_id` would
     * collapse every shipped row into a single survivor and wipe every
     * earlier game's streaks and totals.
     */
    public const CONTROL_PLANE_TABLES = [
        'users' => ['user_key' => 'id', 'row_key' => 'id'],
        'manager_stats' => ['user_key' => 'user_id', 'row_key' => 'id'],
    ];

    /**
     * Tenant-plane tables, listed in the order an import should write them so
     * foreign keys are satisfied.
     *
     * The first entry ('games') is the root: it must be inserted before any
     * row that has a `game_id`.
     *
     * @return list<string>
     */
    public const TENANT_TABLES_IN_INSERT_ORDER = [
        // Root.
        'games',

        // Direct children of games (depend only on game_id).
        // game_players and cup_ties must come before game_matches because
        // game_matches.mvp_player_id is an FK to game_players.id and
        // game_matches.cup_tie_id is an FK to cup_ties.id.
        'game_players',
        'cup_ties',
        'game_matches',
        'game_standings',
        'game_tactics',
        'game_tactical_presets',
        'competition_entries',
        'team_reputations',
        'game_finances',
        'game_investments',
        'game_notifications',
        'game_transfers',
        'transfer_offers',
        'transfer_listings',
        'loans',
        'budget_loans',
        'scout_reports',
        'shortlisted_players',
        'renewal_negotiations',
        'academy_players',
        'player_suspensions',
        'season_archives',
        'season_ticket_pricings',
        'simulated_seasons',
        'manager_trophies',
        'activation_events',

        // Match-scoped (FK: game_match_id → game_matches.id).
        'match_events',
        'match_attendances',

        // Player-scoped (FK: game_player_id → game_players.id).
        'game_player_match_state',
        'user_squad_career_records',

        // Mixed scope (game_id + nullable related_player_id).
        'financial_transactions',
    ];
}
