<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Curated per-club fan_loyalty anchor on a coarse 0-10 editorial scale.
 * Captures a club's cultural stadium-filling power independent of its
 * competitive tier. 10 = iconic (Athletic Club, St. Pauli, Celtic); 5 = average
 * (the default for any club not explicitly curated); 4 and below =
 * notably low-following. See ClubProfile::FAN_LOYALTY_* constants for the
 * full rubric.
 *
 * Scaled ×10 into TeamReputation.base_loyalty at game setup by
 * SetupNewGame; never mutates after that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('fan_loyalty')->default(5)->after('reputation_level');
        });
    }

    public function down(): void
    {
        Schema::table('club_profiles', function (Blueprint $table) {
            $table->dropColumn('fan_loyalty');
        });
    }
};
