<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_notifications', function (Blueprint $table) {
            $table->date('game_date')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('game_notifications', function (Blueprint $table) {
            $table->dropColumn('game_date');
        });
    }
};
