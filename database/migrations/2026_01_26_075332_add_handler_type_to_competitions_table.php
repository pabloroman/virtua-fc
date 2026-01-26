<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->string('handler_type', 30)->default('league')->after('type');
        });

        // Update existing competitions with appropriate handler types
        DB::table('competitions')
            ->where('type', 'cup')
            ->update(['handler_type' => 'knockout_cup']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn('handler_type');
        });
    }
};
