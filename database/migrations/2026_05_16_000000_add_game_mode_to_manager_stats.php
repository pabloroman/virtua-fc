<?php

use App\Models\Game;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Use a literal DEFAULT so PG 11+ treats this as a metadata-only
        // ALTER (no table rewrite, no per-row UPDATE). Pro Manager mode is
        // introduced in this deployment, so every existing row is logically
        // a regular career — the default supplies that without backfilling.
        Schema::table('manager_stats', function (Blueprint $table) {
            $table->string('game_mode', 16)
                ->default(Game::MODE_CAREER)
                ->after('team_id');
            $table->index('game_mode');
        });
    }

    public function down(): void
    {
        Schema::table('manager_stats', function (Blueprint $table) {
            $table->dropIndex(['game_mode']);
            $table->dropColumn('game_mode');
        });
    }
};
