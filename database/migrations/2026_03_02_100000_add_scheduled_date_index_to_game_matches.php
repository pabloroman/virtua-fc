<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->index(['game_id', 'played', 'scheduled_date']);
        });

        Schema::table('game_notifications', function (Blueprint $table) {
            $table->index(['game_id', 'type', 'game_date']);
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropIndex(['game_id', 'played', 'scheduled_date']);
        });

        Schema::table('game_notifications', function (Blueprint $table) {
            $table->dropIndex(['game_id', 'type', 'game_date']);
        });
    }
};
