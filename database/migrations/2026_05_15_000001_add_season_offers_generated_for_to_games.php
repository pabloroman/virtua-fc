<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the season for which between-seasons pro-manager offers have
 * been generated for a game. JobOfferService::ensureEndOfSeasonOffersGenerated
 * stamps this column when it runs (even if the resulting plan creates zero
 * offer rows, e.g. a "below" grade with no interest from other clubs).
 *
 * Without this marker, hasResolvedOffersFor cannot distinguish "no offers
 * yet generated" (must redirect to /season-offers) from "generation ran
 * and produced zero rows" (must fall through to the closing pipeline) —
 * those two states look identical when reading ManagerJobOffer alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('season_offers_generated_for')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('season_offers_generated_for');
        });
    }
};
