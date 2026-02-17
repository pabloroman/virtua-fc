<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add type column to teams (club vs national)
        Schema::table('teams', function (Blueprint $table) {
            $table->string('type', 20)->default('club')->after('id');
        });

        // Add group_label to game_standings for WC group stage
        Schema::table('game_standings', function (Blueprint $table) {
            $table->char('group_label', 1)->nullable()->after('competition_id');
        });
    }

    public function down(): void
    {
        Schema::table('game_standings', function (Blueprint $table) {
            $table->dropColumn('group_label');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
