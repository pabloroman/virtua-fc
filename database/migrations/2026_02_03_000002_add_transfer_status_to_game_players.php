<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->string('transfer_status')->nullable()->after('season_appearances');
            $table->timestamp('transfer_listed_at')->nullable()->after('transfer_status');
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn(['transfer_status', 'transfer_listed_at']);
        });
    }
};
