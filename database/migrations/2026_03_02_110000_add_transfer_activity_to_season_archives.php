<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('season_archives', function (Blueprint $table) {
            $table->json('transfer_activity')->nullable()->after('match_results');
        });
    }

    public function down(): void
    {
        Schema::table('season_archives', function (Blueprint $table) {
            $table->dropColumn('transfer_activity');
        });
    }
};
