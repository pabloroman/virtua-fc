<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_teams', function (Blueprint $table) {
            $table->char('group_label', 1)->nullable()->after('season');
        });
    }

    public function down(): void
    {
        Schema::table('competition_teams', function (Blueprint $table) {
            $table->dropColumn('group_label');
        });
    }
};
