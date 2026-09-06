<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give existing saves the French and Italian cup fields.
 *
 * The same gap the English backfill closed: SetupNewGame::copyCompetitionTeamsToGame
 * only runs for a game with no competition_entries at all, so saves created
 * before the Coupe de France, Trophée des Champions, Coppa Italia and
 * Supercoppa existed never receive their participants. Left alone, the next
 * season transition would rebuild each cup from tier 1 only — 18 or 20 clubs,
 * an odd pool a round or two in, which ConductNextCupRoundDraw swallows and
 * the cup silently stops.
 *
 * Copy each game's entries from competition_teams for the game's own base
 * season, which is exactly what a fresh save would have been given — entry
 * rounds included, so the Coppa Italia's byes survive.
 */
return new class extends Migration
{
    private const CUP_IDS = ['FRACUP', 'FRASUP', 'ITACUP', 'ITASUP'];

    public function up(): void
    {
        // The competitions themselves only exist once app:seed-reference-data
        // has run against data that includes them; until then there is
        // nothing to copy and the FK would reject the insert anyway.
        $seededCups = DB::table('competitions')
            ->whereIn('id', self::CUP_IDS)
            ->pluck('id')
            ->all();

        if (empty($seededCups)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($seededCups), '?'));

        DB::statement(
            "INSERT INTO competition_entries (game_id, competition_id, team_id, entry_round)
             SELECT g.id, ct.competition_id, ct.team_id, ct.entry_round
             FROM games g
             JOIN competition_teams ct
               ON ct.season = g.base_season
              AND ct.competition_id IN ({$placeholders})
             ON CONFLICT (game_id, competition_id, team_id) DO NOTHING",
            $seededCups,
        );
    }

    public function down(): void
    {
        DB::table('competition_entries')
            ->whereIn('competition_id', self::CUP_IDS)
            ->delete();
    }
};
