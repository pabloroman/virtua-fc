<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('activation_events')
            ->where('event', 'matchday_5_reached')
            ->update(['event' => '5_matches_played']);
    }

    public function down(): void
    {
        DB::table('activation_events')
            ->where('event', '5_matches_played')
            ->update(['event' => 'matchday_5_reached']);
    }
};
