<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['cup_round', 'cup_eliminated']);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedTinyInteger('cup_round')->default(0)->after('current_matchday');
            $table->boolean('cup_eliminated')->default(false)->after('cup_round');
        });
    }
};
