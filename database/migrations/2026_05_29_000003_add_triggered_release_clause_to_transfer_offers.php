<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_offers', function (Blueprint $table) {
            // Marks an offer created by paying a player's release clause: the
            // selling club cannot refuse the fee (offer starts at fee_agreed).
            $table->boolean('triggered_release_clause')->default(false)->after('transfer_fee');
        });
    }

    public function down(): void
    {
        Schema::table('transfer_offers', function (Blueprint $table) {
            $table->dropColumn('triggered_release_clause');
        });
    }
};
