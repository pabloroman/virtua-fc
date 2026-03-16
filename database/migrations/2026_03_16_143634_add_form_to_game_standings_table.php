<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_standings', function (Blueprint $table) {
            $table->string('form', 10)->nullable()->after('points');
        });
    }

    public function down(): void
    {
        Schema::table('game_standings', function (Blueprint $table) {
            $table->dropColumn('form');
        });
    }
};
