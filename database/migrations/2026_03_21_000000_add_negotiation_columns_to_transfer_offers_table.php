<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_offers', function (Blueprint $table) {
            // Club fee negotiation columns
            $table->unsignedTinyInteger('negotiation_round')->nullable();
            $table->float('disposition')->nullable();

            // Personal terms negotiation columns
            $table->string('terms_status')->nullable();
            $table->unsignedTinyInteger('terms_round')->nullable();
            $table->float('terms_disposition')->nullable();
            $table->bigInteger('player_demand')->nullable();
            $table->unsignedTinyInteger('preferred_years')->nullable();
            $table->unsignedTinyInteger('offered_years')->nullable();
            $table->bigInteger('wage_counter_offer')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transfer_offers', function (Blueprint $table) {
            $table->dropColumn([
                'negotiation_round',
                'disposition',
                'terms_status',
                'terms_round',
                'terms_disposition',
                'player_demand',
                'preferred_years',
                'offered_years',
                'wage_counter_offer',
            ]);
        });
    }
};
