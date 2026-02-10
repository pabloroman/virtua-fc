<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->uuid('game_player_id');
            $table->uuid('offering_team_id');

            // 'listed' = user put player for sale, 'unsolicited' = AI poaching
            $table->string('offer_type');

            $table->bigInteger('transfer_fee'); // In cents

            // 'pending', 'accepted', 'rejected', 'expired', 'completed'
            $table->string('status')->default('pending');

            $table->date('expires_at');
            $table->date('game_date');       // In-game date when offer was created
            $table->date('resolved_at')->nullable(); // In-game date when status was finalized

            $table->foreign('game_id')
                ->references('id')
                ->on('games')
                ->cascadeOnDelete();

            $table->foreign('game_player_id')
                ->references('id')
                ->on('game_players')
                ->cascadeOnDelete();

            $table->foreign('offering_team_id')
                ->references('id')
                ->on('teams');

            // Index for common queries
            $table->index(['game_id', 'status']);
            $table->index(['game_player_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_offers');
    }
};
