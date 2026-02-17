<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academy_players', function (Blueprint $table) {
            $table->boolean('evaluation_needed')->default(false)->after('is_on_loan');
        });
    }

    public function down(): void
    {
        Schema::table('academy_players', function (Blueprint $table) {
            $table->dropColumn('evaluation_needed');
        });
    }
};
