<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Beta cutover cleanup. After beta.virtuafc.com is disconnected and the DNS
 * is redirected to play.virtuafc.com, no user can complete a real import —
 * the export endpoints are gone. Flip every remaining unmigrated user to
 * `completed` so RequireMigrationOnImport stops redirecting them to the
 * import page; they land on a normal (empty) dashboard instead, the same
 * experience a brand-new signup gets.
 *
 * Merge this migration only AFTER the export deployment is offline. Running
 * it earlier would short-circuit legitimate in-flight migrations.
 *
 * Once this has run on production, MIGRATION_MODE can safely be set to
 * `off` — the middleware no-ops for `completed` users regardless of mode.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereIn('migration_status', ['pending', 'in_progress', 'failed'])
            ->update([
                'migration_status' => 'completed',
                'migration_completed_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Irreversible: we don't record which users were originally in which
        // pre-cutover state, so there's no honest way to restore it.
    }
};
