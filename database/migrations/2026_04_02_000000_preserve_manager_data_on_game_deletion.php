<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // manager_stats: change game_id FK from cascadeOnDelete to nullOnDelete
        Schema::table('manager_stats', function (Blueprint $table) {
            $table->dropForeign(['game_id']);
            $table->foreign('game_id')->references('id')->on('games')->nullOnDelete();
        });

        // manager_trophies: make game_id nullable and change FK to nullOnDelete
        Schema::table('manager_trophies', function (Blueprint $table) {
            $table->dropForeign(['game_id']);
            $table->uuid('game_id')->nullable()->change();
            $table->foreign('game_id')->references('id')->on('games')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('manager_stats', function (Blueprint $table) {
            $table->dropForeign(['game_id']);
            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
        });

        Schema::table('manager_trophies', function (Blueprint $table) {
            $table->dropForeign(['game_id']);
            $table->uuid('game_id')->nullable(false)->change();
            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
        });
    }
};
