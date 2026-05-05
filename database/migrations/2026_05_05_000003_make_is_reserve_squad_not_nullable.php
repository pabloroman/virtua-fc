<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 3 of the is_reserve_squad denormalization. Tightens the column on
     * both planes to NOT NULL DEFAULT false now that the backfill has run and
     * every existing row carries a value.
     */
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->boolean('is_reserve_squad')->default(false)->nullable(false)->change();
        });

        Schema::connection('pgsql_control')->table('game_player_templates', function (Blueprint $table) {
            $table->boolean('is_reserve_squad')->default(false)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->boolean('is_reserve_squad')->nullable()->change();
        });

        Schema::connection('pgsql_control')->table('game_player_templates', function (Blueprint $table) {
            $table->boolean('is_reserve_squad')->nullable()->change();
        });
    }
};
