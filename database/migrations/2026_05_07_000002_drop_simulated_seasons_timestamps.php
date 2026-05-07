<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-game tenant tables follow the in-game calendar (Game::current_date),
 * not wall-clock time, so created_at / updated_at carry no useful signal.
 * The original simulated_seasons migration accidentally included them; drop
 * them now to align with the rest of the tenant-plane schema (no
 * $table->timestamps()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simulated_seasons', function (Blueprint $table) {
            $table->dropColumn(['created_at', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('simulated_seasons', function (Blueprint $table) {
            $table->timestamps();
        });
    }
};
