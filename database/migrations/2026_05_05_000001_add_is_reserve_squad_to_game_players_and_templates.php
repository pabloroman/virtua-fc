<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 1 of denormalizing the reserve-squad flag onto game_players and
     * game_player_templates so TransferMarketService::loadRostersFor doesn't
     * have to JOIN the control-plane teams table to filter on parent_team_id.
     *
     * Columns are nullable here; backfill and the NOT NULL flip land in the
     * two sibling migrations.
     */
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->boolean('is_reserve_squad')->nullable()->after('team_id');
        });

        Schema::connection('pgsql_control')->table('game_player_templates', function (Blueprint $table) {
            $table->boolean('is_reserve_squad')->nullable()->after('team_id');
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn('is_reserve_squad');
        });

        Schema::connection('pgsql_control')->table('game_player_templates', function (Blueprint $table) {
            $table->dropColumn('is_reserve_squad');
        });
    }
};
