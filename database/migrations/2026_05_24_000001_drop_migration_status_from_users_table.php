<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beta cutover cleanup, second pass. The 2026_05_10 migration flipped every
 * remaining user to `completed`; with the export/import harness now removed
 * from the codebase, the columns themselves are dead weight.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['migration_status']);
            $table->dropColumn(['migration_status', 'migration_completed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('migration_status', 20)->default('completed');
            $table->timestampTz('migration_completed_at')->nullable();
            $table->index('migration_status');
        });
    }
};
