<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->string('type'); // player_injured, player_suspended, transfer_offer_received, etc.
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('icon')->nullable();
            $table->string('priority')->default('info'); // critical, warning, info
            $table->json('metadata')->nullable(); // player_id, offer_id, amounts, etc.
            $table->timestamp('read_at')->nullable();

            $table->foreign('game_id')
                ->references('id')
                ->on('games')
                ->cascadeOnDelete();

            $table->index(['game_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_notifications');
    }
};
