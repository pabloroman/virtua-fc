<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renewal_negotiations', function (Blueprint $table) {
            // The release clause (cents) the manager set during the renewal chat.
            // Persisted on the negotiation so it survives counter-offer rounds and
            // is available when the user accepts a player counter (which carries no
            // payload). Null = clause untouched (mandatory ES floor applies).
            $table->unsignedBigInteger('release_clause_requested')->nullable()->after('offered_years');
        });
    }

    public function down(): void
    {
        Schema::table('renewal_negotiations', function (Blueprint $table) {
            $table->dropColumn('release_clause_requested');
        });
    }
};
