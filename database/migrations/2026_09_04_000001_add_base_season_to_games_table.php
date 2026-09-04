<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pin each game to the reference-data season it was created from.
     *
     * Until now the base season — the `data/{season}/` folder a game reads its
     * schedules from, and the origin its fixture dates are offset against —
     * came from global state (`Competition::season`, `config('season.current')`).
     * That is one row per competition shared by every save, so bumping the base
     * season for a new release re-pointed it for careers already in flight and
     * made them compute a negative year offset.
     *
     * Existing rows are backfilled with the literal '2025' rather than
     * config('season.current'): 2025 is the only base season ever shipped, and
     * a literal keeps the backfill correct even if this migration is run after
     * GAME_SEASON has already been bumped.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('base_season', 10)->nullable()->after('season');
        });

        DB::table('games')->whereNull('base_season')->update(['base_season' => '2025']);
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('base_season');
        });
    }
};
