<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_stadiums', function (Blueprint $table) {
            // Game-calendar date of the club's most recent "seek sponsors"
            // search. Drives the search cooldown on the Commercial page; null
            // means the club has never sought a naming-rights sponsor.
            $table->date('naming_rights_last_sought_date')->nullable()->after('name_changed_season');
        });
    }

    public function down(): void
    {
        Schema::table('game_stadiums', function (Blueprint $table) {
            $table->dropColumn('naming_rights_last_sought_date');
        });
    }
};
