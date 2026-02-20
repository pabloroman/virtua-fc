<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalize competition role values to be intrinsic (what the competition IS)
 * rather than relative (what it means to a specific game/country).
 *
 * Before: 'primary' (player's league), 'foreign' (other country's league)
 * After:  'league' (any domestic tier league), 'team_pool' (EUR club pool)
 *
 * 'domestic_cup' and 'european' remain unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('competitions')
            ->whereIn('role', ['primary', 'foreign'])
            ->update(['role' => 'league']);

        // The EUR team pool was previously 'foreign' — give it a distinct role
        DB::table('competitions')
            ->where('id', 'EUR')
            ->where('handler_type', 'team_pool')
            ->update(['role' => 'team_pool']);
    }

    public function down(): void
    {
        // Restore original values based on structural properties
        // Tier competitions in the main playable country revert to 'primary'
        DB::table('competitions')
            ->where('role', 'league')
            ->where('country', 'ES')
            ->where('tier', '>=', 1)
            ->update(['role' => 'primary']);

        // All other 'league' roles revert to 'foreign'
        DB::table('competitions')
            ->where('role', 'league')
            ->update(['role' => 'foreign']);

        // team_pool reverts to 'foreign'
        DB::table('competitions')
            ->where('role', 'team_pool')
            ->update(['role' => 'foreign']);
    }
};
