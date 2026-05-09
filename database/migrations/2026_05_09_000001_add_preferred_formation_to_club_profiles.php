<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Curated per-club tactical identity. When set, FormationRecommender biases
 * AI formation choice toward this shape so different clubs feel tactically
 * distinct (Atleti 4-4-2, Barça 4-3-3, Bilbao 4-2-3-1, etc.). Null clubs
 * fall back to a reputation-tier formation bias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_profiles', function (Blueprint $table) {
            $table->string('preferred_formation', 10)->nullable()->after('fan_loyalty');
        });
    }

    public function down(): void
    {
        Schema::table('club_profiles', function (Blueprint $table) {
            $table->dropColumn('preferred_formation');
        });
    }
};
