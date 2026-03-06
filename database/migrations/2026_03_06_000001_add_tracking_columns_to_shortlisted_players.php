<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shortlisted_players', function (Blueprint $table) {
            $table->tinyInteger('intel_level')->default(0);
            $table->boolean('is_tracking')->default(false);
            $table->smallInteger('matchdays_tracked')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('shortlisted_players', function (Blueprint $table) {
            $table->dropColumn(['intel_level', 'is_tracking', 'matchdays_tracked']);
        });
    }
};
