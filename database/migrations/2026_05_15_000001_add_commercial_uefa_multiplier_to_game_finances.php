<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            // UEFA-category commercial revenue multiplier applied at
            // projection time, stored in basis points (10000 = 1.0×).
            // Persisted so next season's projection can strip this factor
            // from the inherited actual before applying the current
            // category's multiplier — that way mid-save stadium upgrades
            // flow into commercial revenue without the bump compounding
            // season after season.
            $table->integer('commercial_uefa_multiplier_bps')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            $table->dropColumn('commercial_uefa_multiplier_bps');
        });
    }
};
