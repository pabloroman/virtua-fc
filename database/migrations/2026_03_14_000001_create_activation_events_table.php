<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activation_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->uuid('game_id')->nullable();
            $table->string('event', 50)->index();
            $table->timestamp('occurred_at');

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('game_id')->references('id')->on('games')->nullOnDelete();

            $table->index(['event', 'occurred_at']);
            $table->unique(['user_id', 'game_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activation_events');
    }
};
