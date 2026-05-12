<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_player_template_tournament_info', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('game_player_template_id');
            $table->boolean('is_injured')->default(false);
            $table->string('club_name')->nullable();
            $table->string('club_crest_url')->nullable();

            $table->foreign('game_player_template_id')
                ->references('id')
                ->on('game_player_templates')
                ->onDelete('cascade');

            $table->unique('game_player_template_id');
        });

        DB::statement('ALTER TABLE game_player_template_tournament_info ALTER COLUMN id SET DEFAULT gen_random_uuid()');
    }

    public function down(): void
    {
        Schema::dropIfExists('game_player_template_tournament_info');
    }
};
