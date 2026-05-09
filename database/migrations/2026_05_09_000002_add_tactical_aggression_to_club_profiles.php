<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Curated per-club tactical aggression on a -2..+2 scale. Captures how
 * much more attacking (or more cautious) a club tends to be than its
 * reputation tier alone would suggest. Used by selectAIMentality /
 * selectAIInstructions to ladder-shift the mentality, pressing,
 * defensive line, and playing-style outputs.
 *
 *   +2 — extreme attacking (Atalanta-Gasperini, Pep peak)
 *   +1 — front-foot, presses regardless of opponent
 *    0 — neutral / tier-typical (the default)
 *   -1 — typically cautious
 *   -2 — extreme low-block (Cholismo, Bordalás)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_profiles', function (Blueprint $table) {
            $table->smallInteger('tactical_aggression')->default(0)->after('preferred_formation');
        });
    }

    public function down(): void
    {
        Schema::table('club_profiles', function (Blueprint $table) {
            $table->dropColumn('tactical_aggression');
        });
    }
};
