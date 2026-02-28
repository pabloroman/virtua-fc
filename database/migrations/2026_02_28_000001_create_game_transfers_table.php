<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignUuid('game_player_id')->constrained('game_players')->cascadeOnDelete();
            $table->foreignUuid('from_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignUuid('to_team_id')->constrained('teams');
            $table->unsignedBigInteger('transfer_fee')->default(0);
            $table->string('type'); // 'transfer', 'free_agent', 'loan'
            $table->string('season');
            $table->string('window'); // 'summer', 'winter'

            $table->index(['game_id', 'season']);
            $table->index(['game_id', 'game_player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_transfers');
    }
};
