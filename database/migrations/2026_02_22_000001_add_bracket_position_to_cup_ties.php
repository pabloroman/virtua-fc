<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cup_ties', function (Blueprint $table) {
            $table->unsignedSmallInteger('bracket_position')->nullable()->after('round_number');
        });
    }

    public function down(): void
    {
        Schema::table('cup_ties', function (Blueprint $table) {
            $table->dropColumn('bracket_position');
        });
    }
};
