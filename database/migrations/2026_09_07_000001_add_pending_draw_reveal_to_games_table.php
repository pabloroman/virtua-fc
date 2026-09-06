<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Queue of cup draws the user has not watched yet. A list rather than a single
 * value because two draws can land in one request: dispatchPostFinalizeEffects
 * conducts a knockout-cup draw through CupTieResolved and then a Swiss knockout
 * round through beforeMatches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->json('pending_draw_reveal')->nullable()->after('pending_actions');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('pending_draw_reveal');
        });
    }
};
