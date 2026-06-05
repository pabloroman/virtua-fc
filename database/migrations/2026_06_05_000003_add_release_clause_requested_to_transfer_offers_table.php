<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_offers', function (Blueprint $table) {
            // The release clause (cents) the manager set during the personal-terms
            // chat when signing a player into the (ES) club — buy transfer, Bosman
            // pre-contract, or free agent. Persisted on the offer so it survives
            // counter rounds and is available at completion (which carries no
            // payload). Null = clause untouched (mandatory ES floor applies).
            $table->unsignedBigInteger('release_clause_requested')->nullable()->after('wage_counter_offer');
        });
    }

    public function down(): void
    {
        Schema::table('transfer_offers', function (Blueprint $table) {
            $table->dropColumn('release_clause_requested');
        });
    }
};
