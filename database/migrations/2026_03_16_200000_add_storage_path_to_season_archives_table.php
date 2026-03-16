<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('season_archives', function (Blueprint $table) {
            $table->string('storage_path')->nullable()->after('season');
        });
    }

    public function down(): void
    {
        Schema::table('season_archives', function (Blueprint $table) {
            $table->dropColumn('storage_path');
        });
    }
};
