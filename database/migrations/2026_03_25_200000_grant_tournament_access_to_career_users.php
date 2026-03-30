<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('has_career_access', true)
            ->update(['has_tournament_access' => true]);
    }

    public function down(): void
    {
        // Intentionally left empty — cannot reliably reverse this data migration
        // since we don't know which career users already had tournament access.
    }
};
