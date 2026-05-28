<?php

use App\Modules\Competition\Services\NeutralVenueResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfills neutral venues for finals (and Spanish Supercup games) that were
 * scheduled before neutral-venue assignment was reliable: finals created
 * outside career mode, finals where the random European venue query yielded
 * null, and Spanish Supercup semi-finals which were never assigned a venue at
 * all. Only touches unplayed matches — already-played fixtures have their
 * attendance recorded, so changing the venue would desync the figure from the
 * stored gate.
 */
return new class extends Migration
{
    public function up(): void
    {
        $resolver = new NeutralVenueResolver();

        $matches = DB::table('game_matches')
            ->whereNull('neutral_venue_name')
            ->where('played', false)
            ->where(function ($query) {
                // Spanish Supercup: every game (semis + final) is neutral.
                $query->where('competition_id', 'ESPSUP')
                    // Cup/UEFA finals at a designated neutral ground.
                    ->orWhere(function ($q) {
                        $q->where('round_name', 'cup.final')
                            ->whereIn('competition_id', ['ESPCUP', 'UCL', 'UEL', 'UECL', 'UEFASUP']);
                    });
            })
            ->get(['id', 'competition_id', 'round_name', 'home_team_id', 'away_team_id']);

        foreach ($matches as $match) {
            $venue = $resolver->resolve(
                $match->competition_id,
                $match->round_name,
                $match->home_team_id,
                $match->away_team_id,
            );

            if (!$venue) {
                continue;
            }

            DB::table('game_matches')->where('id', $match->id)->update([
                'neutral_venue_name' => $venue['name'],
                'neutral_venue_capacity' => $venue['capacity'],
            ]);
        }
    }

    public function down(): void
    {
        // Data backfill — nothing to reverse.
    }
};
