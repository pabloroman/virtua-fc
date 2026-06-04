<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_stadium_naming_deals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->uuid('team_id');

            // The sponsoring brand and the stadium name it imposes.
            $table->string('sponsor_name');
            $table->string('proposed_stadium_name');

            // Full-house headline value (cents). Settlement scales this by the
            // realised stadium fill, so an emptier ground earns less.
            $table->bigInteger('annual_value_cents');

            // 1–5 seasons, chosen at signing.
            $table->smallInteger('contract_seasons');

            // pending | active | expired | rejected
            $table->string('status')->default('pending');

            // Season this offer was generated in.
            $table->integer('offered_season');

            // Set when an offer is accepted; end = start + contract_seasons - 1.
            $table->integer('start_season')->nullable();
            $table->integer('end_season')->nullable();

            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->foreign('team_id')->references('id')->on('teams');

            $table->index(['game_id', 'team_id', 'status']);
        });

        DB::statement('ALTER TABLE game_stadium_naming_deals ALTER COLUMN id SET DEFAULT gen_random_uuid()');
    }

    public function down(): void
    {
        Schema::dropIfExists('game_stadium_naming_deals');
    }
};
