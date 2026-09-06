<?php

use App\Models\MatchEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Let a match event name a scorer who is nobody.
 *
 * A squad-less cup entrant — a ghost — can score, and its goal has to be a real
 * `match_events` row: the score is recomputed from events, so a goal without one
 * disappears when the match is re-simulated. There is no player to credit, so
 * the row carries `MatchEvent::UNATTRIBUTED_PLAYER_ID`, which references no
 * `game_players` row. The foreign key has to go for that to insert.
 *
 * The column stays NOT NULL, so a genuinely missing scorer still fails loudly;
 * exactly one known value is exempt.
 *
 * Losing `ON DELETE CASCADE` costs nothing in practice. The only place the
 * application deletes a `GamePlayer` is `PlayerRetirementProcessor` (priority
 * 40), and `SeasonArchiveProcessor` (priority 25) has already deleted the game's
 * match events earlier in the same closing pipeline. `SeedWorldCupData` deletes
 * its match events explicitly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_events', function (Blueprint $table) {
            $table->dropForeign(['game_player_id']);
        });
    }

    public function down(): void
    {
        // The rows the constraint cannot describe have to go before it comes back.
        DB::table('match_events')
            ->where('game_player_id', MatchEvent::UNATTRIBUTED_PLAYER_ID)
            ->delete();

        Schema::table('match_events', function (Blueprint $table) {
            $table->foreign('game_player_id')->references('id')->on('game_players')->onDelete('cascade');
        });
    }
};
