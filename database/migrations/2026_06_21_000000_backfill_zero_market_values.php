<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Contracted (team_id) players whose template carried no quoted market
        // value were persisted at 0 cents and render as "Free" / "€ 0" in the
        // transfer market and scouting screens. Floor them at €100K to match the
        // corrected template generation (GamePlayerTemplateService). National-team
        // (tournament / World Cup) squads are excluded — their 0 values are set
        // intentionally and have no transfer market.
        //
        // tier needs no update: €100K is below PlayerTierService::TIER_2_MIN (€1M),
        // so these rows are already tier 1, which is what a raw 0 produced too.
        DB::table('game_players')
            ->where('market_value_cents', '<=', 0)
            ->whereNotNull('team_id')
            ->whereNotIn('team_id', function ($query) {
                $query->select('id')->from('teams')->where('type', 'national');
            })
            ->update(['market_value_cents' => 10_000_000]);
    }

    public function down(): void
    {
        // No-op: the original 0 values were a bug and are not recoverable.
    }
};
