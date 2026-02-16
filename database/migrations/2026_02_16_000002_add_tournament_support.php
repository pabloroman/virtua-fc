<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop WC-specific tables (data now lives in teams/players)
        Schema::dropIfExists('wc_players');
        Schema::dropIfExists('wc_teams');

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

        // Recreate WC tables
        Schema::create('wc_teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('short_name', 10);
            $table->char('country_code', 2);
            $table->string('confederation', 10);
            $table->string('image')->nullable();
            $table->unsignedTinyInteger('strength')->default(50);
            $table->unsignedTinyInteger('pot')->default(4);
            $table->unique('country_code');
        });

        Schema::create('wc_players', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('wc_team_id');
            $table->string('name');
            $table->date('date_of_birth')->nullable();
            $table->json('nationality')->nullable();
            $table->string('height')->nullable();
            $table->string('foot')->nullable();
            $table->string('position');
            $table->unsignedTinyInteger('number')->nullable();
            $table->unsignedTinyInteger('technical_ability');
            $table->unsignedTinyInteger('physical_ability');
            $table->foreign('wc_team_id')->references('id')->on('wc_teams')->cascadeOnDelete();
            $table->index('wc_team_id');
        });
    }
};
