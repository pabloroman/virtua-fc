<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_tactics', function (Blueprint $table) {
            $table->json('default_pitch_positions')->nullable()->after('default_slot_assignments');
        });
    }

    public function down(): void
    {
        Schema::table('game_tactics', function (Blueprint $table) {
            $table->dropColumn('default_pitch_positions');
        });
    }
};
