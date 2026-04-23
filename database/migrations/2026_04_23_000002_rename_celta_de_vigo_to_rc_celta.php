<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rename the club to its official registered name. Keyed by
     * transfermarkt_id (stable identifier) rather than name, so this migration
     * is safe to re-run and independent of whichever name the row currently
     * holds.
     */
    public function up(): void
    {
        DB::table('teams')
            ->where('transfermarkt_id', 940)
            ->update([
                'name' => 'RC Celta',
                'slug' => 'rc-celta',
            ]);
    }

    public function down(): void
    {
        DB::table('teams')
            ->where('transfermarkt_id', 940)
            ->update([
                'name' => 'Celta de Vigo',
                'slug' => 'celta-de-vigo',
            ]);
    }
};
