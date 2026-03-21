<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_player_templates', function (Blueprint $table) {
            $table->uuid('team_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('game_player_templates', function (Blueprint $table) {
            $table->uuid('team_id')->nullable(false)->change();
        });
    }
};
