<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_player_templates', function (Blueprint $table) {
            $table->json('secondary_positions')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('game_player_templates', function (Blueprint $table) {
            $table->dropColumn('secondary_positions');
        });
    }
};
