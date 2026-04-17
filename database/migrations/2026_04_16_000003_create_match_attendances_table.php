<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per played (or about-to-be-played) fixture, written by
     * MatchAttendanceService before simulation runs. capacity_at_match is
     * snapshotted so historical rows survive future capacity expansions.
     */
    public function up(): void
    {
        Schema::create('match_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->uuid('game_match_id');

            $table->unsignedInteger('attendance');
            $table->unsignedInteger('capacity_at_match');

            $table->timestamp('created_at')->useCurrent();

            $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
            $table->foreign('game_match_id')->references('id')->on('game_matches')->onDelete('cascade');

            $table->unique('game_match_id');
            $table->index('game_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_attendances');
    }
};
