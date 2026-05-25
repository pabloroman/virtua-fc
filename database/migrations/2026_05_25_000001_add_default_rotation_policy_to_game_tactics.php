<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_tactics', function (Blueprint $table) {
            $table->string('default_rotation_policy')->default('balanced')->after('default_defensive_line');
        });
    }

    public function down(): void
    {
        Schema::table('game_tactics', function (Blueprint $table) {
            $table->dropColumn('default_rotation_policy');
        });
    }
};
