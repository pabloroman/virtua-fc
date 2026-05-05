<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop every foreign key constraint that crosses the tenant/control plane
 * boundary. Columns themselves are kept — they remain valid logical
 * references — but the database-level enforcement goes away because it
 * cannot survive a physical plane split (FKs cannot span databases).
 *
 * Includes both directions:
 *  - tenant table → control table (the common case)
 *  - control table → tenant table (manager_stats, manager_trophies' game_id)
 *
 * Same-plane FKs (game_id → games on tenant tables, intra-control FKs on
 * users/teams/etc.) are untouched.
 */
return new class extends Migration
{
    /**
     * @var list<array{table: string, column: string, schema?: string}>
     *
     * Each entry drops a single FK. `schema` defaults to 'pgsql' (tenant);
     * use 'pgsql_control' for control-plane tables.
     */
    private array $tenantToControl = [
        // games → teams / competitions
        ['table' => 'games', 'column' => 'team_id'],
        ['table' => 'games', 'column' => 'competition_id'],
        ['table' => 'games', 'column' => 'reserve_team_id'],

        // game_matches → teams / competitions
        ['table' => 'game_matches', 'column' => 'home_team_id'],
        ['table' => 'game_matches', 'column' => 'away_team_id'],
        ['table' => 'game_matches', 'column' => 'competition_id'],

        // game_standings → teams / competitions
        ['table' => 'game_standings', 'column' => 'team_id'],
        ['table' => 'game_standings', 'column' => 'competition_id'],

        // competition_entries → teams / competitions
        ['table' => 'competition_entries', 'column' => 'team_id'],
        ['table' => 'competition_entries', 'column' => 'competition_id'],

        // cup_ties → teams / competitions
        ['table' => 'cup_ties', 'column' => 'home_team_id'],
        ['table' => 'cup_ties', 'column' => 'away_team_id'],
        ['table' => 'cup_ties', 'column' => 'winner_id'],
        ['table' => 'cup_ties', 'column' => 'competition_id'],

        // simulated_seasons → competitions
        ['table' => 'simulated_seasons', 'column' => 'competition_id'],

        // transfer_listings → teams
        ['table' => 'transfer_listings', 'column' => 'team_id'],

        // transfer_offers → teams (offering_team_id from create migration,
        // selling_team_id added later)
        ['table' => 'transfer_offers', 'column' => 'offering_team_id'],
        ['table' => 'transfer_offers', 'column' => 'selling_team_id'],

        // game_transfers → teams
        ['table' => 'game_transfers', 'column' => 'from_team_id'],
        ['table' => 'game_transfers', 'column' => 'to_team_id'],

        // loans → teams
        ['table' => 'loans', 'column' => 'parent_team_id'],
        ['table' => 'loans', 'column' => 'loan_team_id'],

        // match_events → teams
        ['table' => 'match_events', 'column' => 'team_id'],

        // user_squad_career_records → teams
        ['table' => 'user_squad_career_records', 'column' => 'team_id'],
    ];

    /**
     * @var list<array{table: string, column: string}>
     *
     * Control → tenant FKs — those tables now sit on pgsql_control but their
     * game_id still pointed at the tenant `games` table.
     */
    private array $controlToTenant = [
        ['table' => 'manager_stats', 'column' => 'game_id'],
        ['table' => 'manager_trophies', 'column' => 'game_id'],
    ];

    public function up(): void
    {
        foreach ($this->tenantToControl as $fk) {
            Schema::table($fk['table'], function (Blueprint $table) use ($fk) {
                $table->dropForeign([$fk['column']]);
            });
        }

        foreach ($this->controlToTenant as $fk) {
            Schema::connection('pgsql_control')->table($fk['table'], function (Blueprint $table) use ($fk) {
                $table->dropForeign([$fk['column']]);
            });
        }
    }

    public function down(): void
    {
        // Recreate each FK so local rollback works. Production never reverses
        // this — the planes are intended to physically split and these FKs
        // can't be re-enforced once they have.
        foreach ($this->controlToTenant as $fk) {
            Schema::connection('pgsql_control')->table($fk['table'], function (Blueprint $table) use ($fk) {
                $table->foreign($fk['column'])->references('id')->on('games');
            });
        }

        foreach (array_reverse($this->tenantToControl) as $fk) {
            $target = match (true) {
                str_ends_with($fk['column'], 'team_id') => 'teams',
                str_ends_with($fk['column'], 'competition_id') => 'competitions',
                $fk['column'] === 'winner_id' => 'teams',
                default => null,
            };

            if ($target === null) {
                continue;
            }

            Schema::table($fk['table'], function (Blueprint $table) use ($fk, $target) {
                $table->foreign($fk['column'])->references('id')->on($target);
            });
        }
    }
};
