<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the standalone Facilities investment lever. Matchday revenue is now
 * driven entirely by the Stadium module (capacity, attendance, ticketing) and
 * commercial growth by the Commercial hub, so the facilities tier — which only
 * applied a 1.0–1.6× multiplier to matchday revenue — is redundant.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Reclaim each club's facilities allocation into its transfer budget.
        // transfer_budget is a running balance (sales credit it, purchases and
        // upgrades debit it), so we add back exactly the facilities spend rather
        // than recomputing from scratch — that preserves all in-season trading.
        // Without this, the stored budget keeps the old facilities deduction and
        // diverges from the (now 3-area) investment page.
        if (Schema::hasColumn('game_investments', 'facilities_amount')) {
            DB::table('game_investments')->update([
                'transfer_budget' => DB::raw('transfer_budget + facilities_amount'),
            ]);
        }

        Schema::table('game_investments', function (Blueprint $table) {
            $table->dropColumn(['facilities_amount', 'facilities_tier']);
        });
    }

    public function down(): void
    {
        Schema::table('game_investments', function (Blueprint $table) {
            $table->bigInteger('facilities_amount')->default(0);
            $table->tinyInteger('facilities_tier')->default(1);
        });
    }
};
