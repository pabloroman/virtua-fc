<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give existing saves the English cup fields.
 *
 * SetupNewGame::copyCompetitionTeamsToGame only runs for a game that has no
 * competition_entries at all, so saves created before the FA Cup, EFL Cup and
 * Community Shield existed never receive their participants. Left alone, an
 * English save's next season transition would rebuild the FA Cup from tier 1
 * only — 20 clubs, which halves to 10 then 5, an odd pool that
 * ConductNextCupRoundDraw swallows, silently killing the cup mid-season.
 *
 * Copy each game's entries from competition_teams for the game's own base
 * season, which is exactly what a fresh save would have been given.
 */
return new class extends Migration
{
    private const CUP_IDS = ['ENGCUP', 'ENGLC', 'ENGSUP'];

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
