<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_suspensions', function (Blueprint $table) {
            $table->unsignedInteger('yellow_cards')->default(0)->after('matches_remaining');
        });
    }

    public function down(): void
    {
        Schema::table('player_suspensions', function (Blueprint $table) {
            $table->dropColumn('yellow_cards');
        });
    }
};
