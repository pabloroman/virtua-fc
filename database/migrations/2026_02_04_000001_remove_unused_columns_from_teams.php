<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'official_name',
                'colors',
                'current_market_value',
                'founded_on',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('official_name')->nullable()->after('name');
            $table->json('colors')->nullable()->after('stadium_seats');
            $table->string('current_market_value')->nullable()->after('colors');
            $table->date('founded_on')->nullable()->after('current_market_value');
        });
    }
};
