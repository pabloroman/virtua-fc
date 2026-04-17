<?php

namespace App\Console\Commands;

use Database\Seeders\ClubProfilesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot to bring the new 0-10 ClubProfile.fan_loyalty values into prod
 * and resync the in-game TeamReputation.base_loyalty + loyalty_points pair
 * on top of the refreshed anchor.
 *
 * Safe to run on production: only touches club_profiles (via the seeder's
 * updateOrCreate) and team_reputations (via the same SQL the loyalty
 * migration runs). Idempotent.
 *
 * Resets loyalty_points to base_loyalty too, because any drift accumulated
 * before this fix was applied to a bogus baseline (the migration column
 * default of 50) — keeping it would carry the bug forward. From the next
 * season close onward, drift starts fresh from the correct anchor.
 */
class ResyncFanLoyalty extends Command
{
    protected $signature = 'app:resync-fan-loyalty';

    protected $description = 'Resync ClubProfile.fan_loyalty (0-10) and TeamReputation loyalty columns';

    public function handle(): int
    {
        $this->info('Refreshing ClubProfile.fan_loyalty from seeder...');
        $this->call('db:seed', [
            '--class' => ClubProfilesSeeder::class,
            '--force' => true,
        ]);

        $this->info('Backfilling TeamReputation.base_loyalty + loyalty_points from ClubProfile × 10...');
        $affected = DB::affectingStatement(<<<SQL
            UPDATE team_reputations tr
            SET base_loyalty   = LEAST(100, GREATEST(0, COALESCE(cp.fan_loyalty, 5) * 10)),
                loyalty_points = LEAST(100, GREATEST(0, COALESCE(cp.fan_loyalty, 5) * 10))
            FROM club_profiles cp
            WHERE tr.team_id = cp.team_id
        SQL);

        $this->info("Updated {$affected} team_reputations rows.");

        return self::SUCCESS;
    }
}
