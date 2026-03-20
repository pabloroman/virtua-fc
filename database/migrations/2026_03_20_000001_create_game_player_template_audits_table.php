<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_player_template_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('game_player_template_id');
            $table->uuid('user_id');
            $table->string('action'); // created, updated, deleted, restored
            $table->json('old_values')->nullable();
            $table->json('new_values');
            $table->timestamp('created_at');

            $table->foreign('game_player_template_id')
                ->references('id')
                ->on('game_player_templates')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users');

            $table->index('game_player_template_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_player_template_audits');
    }
};
