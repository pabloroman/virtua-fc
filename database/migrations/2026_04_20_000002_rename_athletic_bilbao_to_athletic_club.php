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
            ->where('transfermarkt_id', 621)
            ->update([
                'name' => 'Athletic Club',
                'slug' => 'athletic-club',
            ]);
    }

    public function down(): void
    {
        DB::table('teams')
            ->where('transfermarkt_id', 621)
            ->update([
                'name' => 'Athletic Bilbao',
                'slug' => 'athletic-bilbao',
            ]);
    }
};
