<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            // Buyout clause (cláusula de rescisión) in cents. A contract
            // attribute: non-null ⟺ under contract. Nullable — free agents and
            // non-clause leagues carry no clause; nulled at contract expiry.
            $table->unsignedBigInteger('release_clause')->nullable()->after('pending_annual_wage');
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn('release_clause');
        });
    }
};
