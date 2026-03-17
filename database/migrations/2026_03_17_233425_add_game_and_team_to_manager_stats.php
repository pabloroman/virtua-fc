<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manager_stats', function (Blueprint $table) {
            $table->foreignUuid('game_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('team_id')->nullable()->after('game_id')->constrained();

            // Drop the unique constraint on user_id (a user can now have multiple rows)
            $table->dropUnique(['user_id']);

            // Add unique constraint on game_id (one row per game)
            $table->unique('game_id');

            // Add index on user_id for filtering
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('manager_stats', function (Blueprint $table) {
            $table->dropUnique(['game_id']);
            $table->dropIndex(['user_id']);
            $table->dropForeign(['game_id']);
            $table->dropForeign(['team_id']);
            $table->dropColumn(['game_id', 'team_id']);
            $table->unique('user_id');
        });
    }
};
