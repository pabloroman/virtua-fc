<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->string('neutral_venue_name')->nullable();
            $table->unsignedInteger('neutral_venue_capacity')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn(['neutral_venue_name', 'neutral_venue_capacity']);
        });
    }
};
