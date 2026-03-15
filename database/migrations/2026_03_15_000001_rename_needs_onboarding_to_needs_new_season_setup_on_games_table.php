<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->renameColumn('needs_onboarding', 'needs_new_season_setup');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->renameColumn('needs_new_season_setup', 'needs_onboarding');
        });
    }
};
