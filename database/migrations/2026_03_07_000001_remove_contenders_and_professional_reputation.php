<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('club_profiles')
            ->where('reputation_level', 'contenders')
            ->update(['reputation_level' => 'continental']);

        DB::table('club_profiles')
            ->where('reputation_level', 'professional')
            ->update(['reputation_level' => 'modest']);
    }

    public function down(): void
    {
        // No reversal — the seeder defines canonical reputation levels
    }
};
