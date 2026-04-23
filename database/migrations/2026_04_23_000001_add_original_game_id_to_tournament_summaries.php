<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_summaries', function (Blueprint $table) {
            $table->uuid('original_game_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_summaries', function (Blueprint $table) {
            $table->dropUnique(['original_game_id']);
            $table->dropColumn('original_game_id');
        });
    }
};
