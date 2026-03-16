<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Final cleanup: drop blob columns after all existing archives
 * have been migrated to object storage via app:migrate-archives-to-storage.
 *
 * IMPORTANT: Run app:migrate-archives-to-storage BEFORE deploying this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('season_archives', function (Blueprint $table) {
            $table->dropColumn([
                'final_standings',
                'player_season_stats',
                'season_awards',
                'match_results',
                'transfer_activity',
                'match_events_archive',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('season_archives', function (Blueprint $table) {
            $table->json('final_standings')->nullable();
            $table->json('player_season_stats')->nullable();
            $table->json('season_awards')->nullable();
            $table->json('match_results')->nullable();
            $table->json('transfer_activity')->nullable();
            $table->binary('match_events_archive')->nullable();
        });
    }
};
