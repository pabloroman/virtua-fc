<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_investments', function (Blueprint $table) {
            // Per-area tier reductions staged to take effect at next season's
            // setup. A mid-season downgrade never claws back committed spend, so
            // a requested reduction is parked here and consumed when the next
            // season's default allocation is computed. Shape: {"scouting": 1}.
            $table->json('staged_downgrades')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('game_investments', function (Blueprint $table) {
            $table->dropColumn('staged_downgrades');
        });
    }
};
