<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_player_templates', function (Blueprint $table) {
            // Owning club's Transfermarkt id when the player is on loan in the
            // source data (he is listed in the BORROWING club's squad, so
            // team_id is the borrowing club and this points at loan.from). A
            // non-null value marks the template as "on loan"; SetupNewGame
            // turns it into a per-game loan that returns the player to the
            // owning club at season end (or frees him if that club isn't in
            // the game).
            $table->string('loan_from_transfermarkt_id')->nullable()->after('team_id');
        });
    }

    public function down(): void
    {
        Schema::table('game_player_templates', function (Blueprint $table) {
            $table->dropColumn('loan_from_transfermarkt_id');
        });
    }
};
