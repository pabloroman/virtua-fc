<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            $table->bigInteger('carried_surplus')->default(0)->after('carried_debt');
        });
    }

    public function down(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            $table->dropColumn('carried_surplus');
        });
    }
};
