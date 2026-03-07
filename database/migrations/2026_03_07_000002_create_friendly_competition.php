<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('competitions')->insertOrIgnore([
            'id' => 'FR',
            'name' => 'game.pre_season',
            'country' => 'INT',
            'flag' => null,
            'tier' => 0,
            'type' => 'friendly',
            'role' => 'friendly',
            'scope' => 'domestic',
            'handler_type' => 'friendly',
            'season' => '2025',
        ]);
    }

    public function down(): void
    {
        DB::table('competitions')->where('id', 'FR')->delete();
    }
};
