<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->timestamp('setup_completed_at')->nullable()->after('needs_onboarding');
        });

        // Backfill existing games as already set up
        DB::table('games')->whereNull('setup_completed_at')->update(['setup_completed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('setup_completed_at');
        });
    }
};
