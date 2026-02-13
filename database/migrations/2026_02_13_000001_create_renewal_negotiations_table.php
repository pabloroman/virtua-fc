<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renewal_negotiations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->uuid('game_player_id');

            $table->string('status')->default('offer_pending'); // offer_pending, player_countered, accepted, player_rejected, club_declined, club_reconsidered, expired
            $table->integer('round')->default(1); // 1-3 (0 for club_declined without negotiation)
            $table->bigInteger('player_demand')->nullable(); // cents, set once at creation
            $table->integer('preferred_years')->nullable(); // player's preferred length, set once
            $table->bigInteger('user_offer')->nullable(); // cents, latest offer
            $table->integer('offered_years')->nullable(); // user's offered length
            $table->bigInteger('counter_offer')->nullable(); // cents
            $table->integer('contract_years')->nullable(); // final agreed years, set on accept
            $table->float('disposition')->nullable(); // calculated at resolution

            $table->timestamps();

            $table->foreign('game_id')
                ->references('id')
                ->on('games')
                ->cascadeOnDelete();

            $table->foreign('game_player_id')
                ->references('id')
                ->on('game_players')
                ->cascadeOnDelete();

            $table->index(['game_id', 'status']);
            $table->index(['game_player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_negotiations');
    }
};
