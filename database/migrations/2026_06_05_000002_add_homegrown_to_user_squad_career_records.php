<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_squad_career_records', function (Blueprint $table) {
            // True for players developed by the club's own pipeline — youth
            // academy or filial/reserve promotion. Kept separate from the
            // display-facing `joined_from` (which snapshots the reserve team's
            // name on a filial promotion) so the origin badge is unaffected and
            // the loyalty check stays a query-free flag read.
            $table->boolean('homegrown')->default(false)->after('joined_from');
        });

        // Academy graduates.
        DB::table('user_squad_career_records')
            ->where('joined_from', 'Academy')
            ->update(['homegrown' => true]);

        // Filial/reserve promotions: the player came up via an internal
        // promotion to the team that currently owns the career record.
        DB::table('user_squad_career_records')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('game_transfers')
                    ->whereColumn('game_transfers.game_player_id', 'user_squad_career_records.game_player_id')
                    ->whereColumn('game_transfers.to_team_id', 'user_squad_career_records.team_id')
                    ->where('game_transfers.type', 'internal_promotion');
            })
            ->update(['homegrown' => true]);
    }

    public function down(): void
    {
        Schema::table('user_squad_career_records', function (Blueprint $table) {
            $table->dropColumn('homegrown');
        });
    }
};
