<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_stadium_naming_deals', function (Blueprint $table) {
            // The stadium name in effect when the deal was signed (a custom
            // rename, or null for the historic name). On expiry the ground
            // reverts to this instead of always dropping to the historic name,
            // so a manager's own rename survives a sponsorship.
            $table->string('previous_stadium_name')->nullable()->after('proposed_stadium_name');
        });
    }

    public function down(): void
    {
        Schema::table('game_stadium_naming_deals', function (Blueprint $table) {
            $table->dropColumn('previous_stadium_name');
        });
    }
};
