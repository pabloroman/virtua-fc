<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academy_players', function (Blueprint $table) {
            $table->uuid('called_up_game_player_id')->nullable()->after('is_on_loan');
            $table->foreign('called_up_game_player_id')
                ->references('id')
                ->on('game_players')
                ->nullOnDelete();
            $table->index('called_up_game_player_id');
        });

        Schema::table('game_players', function (Blueprint $table) {
            $table->boolean('is_academy_callup')->default(false)->after('tier');
            $table->index('is_academy_callup');
        });
    }

    public function down(): void
    {
        Schema::table('academy_players', function (Blueprint $table) {
            $table->dropForeign(['called_up_game_player_id']);
            $table->dropColumn('called_up_game_player_id');
        });

        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn('is_academy_callup');
        });
    }
};
