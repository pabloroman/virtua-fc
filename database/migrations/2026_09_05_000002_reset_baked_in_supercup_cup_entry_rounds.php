<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clears the supercup skip-ahead that the seeder used to bake into
 * competition_teams.entry_round.
 *
 * That column now holds only what a cup's teams.json declares; the
 * per-game skip-ahead is derived each season by CupEntryRoundService.
 * Databases seeded before that change still carry cup_entry_round on the
 * clubs that happened to be in the supercup the season the data was
 * scraped, and CupEntryRoundService reads the column as its baseline —
 * so those clubs stay parked at the round of 32 while the season's real
 * supercup field is bumped there as well.
 *
 * Round one then loses more clubs than the cup's size budgeted for. With
 * three of Spain's four 2025 Supercopa clubs re-qualifying, five clubs
 * sat at round 3 instead of four and the Copa's first round was drawn
 * from 111 teams — an odd pool, which fails the draw and takes the whole
 * season transition down with it (OddCupDrawPoolException).
 *
 * Reverses the old rule exactly: a cup row is reset only when it sits at
 * the country's cup_entry_round *and* the club was in that same season's
 * supercup field, which is the only way the old seeder ever wrote it.
 * A round a data file declares itself is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('countries', []) as $country) {
            $supercup = $country['supercup'] ?? null;
            $cupId = $supercup['cup'] ?? null;
            $supercupId = $supercup['competition'] ?? null;
            $bumpRound = (int) ($supercup['cup_entry_round'] ?? 0);

            if ($cupId === null || $supercupId === null || $bumpRound <= 1) {
                continue;
            }

            $supercupTeamsBySeason = DB::table('competition_teams')
                ->where('competition_id', $supercupId)
                ->get(['season', 'team_id'])
                ->groupBy('season');

            foreach ($supercupTeamsBySeason as $season => $rows) {
                DB::table('competition_teams')
                    ->where('competition_id', $cupId)
                    ->where('season', $season)
                    ->where('entry_round', $bumpRound)
                    ->whereIn('team_id', $rows->pluck('team_id')->all())
                    ->update(['entry_round' => 1]);
            }
        }
    }

    public function down(): void
    {
        // Intentionally left empty — the rounds this clears were derived
        // state that no longer belongs in the column, so re-baking them
        // would only reintroduce the bug.
    }
};
