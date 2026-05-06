<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Tracks the per-user beta→prod migration. Values:
            //   pending      — not yet migrated (default for all existing users)
            //   in_progress  — import job is running
            //   completed    — import succeeded; on the export side, also locks
            //                  the user out so they don't keep playing on beta
            //   failed       — last attempt failed; user can retry
            $table->string('migration_status', 20)->default('pending');
            $table->timestampTz('migration_completed_at')->nullable();
            $table->index('migration_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['migration_status']);
            $table->dropColumn(['migration_status', 'migration_completed_at']);
        });
    }
};
