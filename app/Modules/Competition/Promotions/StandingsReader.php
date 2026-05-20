<?php

namespace App\Modules\Competition\Promotions;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use Illuminate\Support\Facades\Log;

/**
 * Reads the final ordered team list for one competition at season close.
 *
 * Source preference, in order:
 *   1. Real GameStanding rows where played > 0 — the actual played season.
 *   2. SimulatedSeason.results — the placeholder finishing order for leagues
 *      the user wasn't in.
 *
 * Stale-roster guard: when SimulatedSeason.results lists team IDs that are no
 * longer in this competition's CompetitionEntry roster (drift caused by prior
 * promotion/relegation swaps that didn't refresh the simulation), the reader
 * rewrites the results in place 1:1, swapping each stale team out for an
 * entry team that's missing from the results. This is the same reconciliation
 * the retired UnstickGame command did manually — pulled into the read path so promotion/relegation
 * cannot consume stale simulated data and double-relegate a team that's
 * already been moved.
 *
 * Reconciliation only runs when in-sim-not-entry and in-entry-not-sim are the
 * same size — otherwise the row is left untouched and a warning is logged so
 * the planner can throw a clear validation error rather than silently picking
 * a half-corrupt list.
 */
class StandingsReader
{
    /**
     * Return the ordered list of teams in the competition, top to bottom.
     *
     * @return list<array{teamId: string, position: int}>
     */
    public function read(Game $game, string $competitionId): array
    {
        $standings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('played', '>', 0)
            ->orderBy('position')
            ->get(['team_id', 'position']);

        if ($standings->isNotEmpty()) {
            return $standings->map(fn ($s) => [
                'teamId' => $s->team_id,
                'position' => (int) $s->position,
            ])->all();
        }

        $simulated = SimulatedSeason::where('game_id', $game->id)
            ->where('season', $game->season)
            ->where('competition_id', $competitionId)
            ->first();

        if (!$simulated) {
            // Fallback to placeholder standings (e.g. position 99 rows inserted by an earlier
            // unsplit move) — used only when nothing else exists.
            $placeholders = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $competitionId)
                ->orderBy('position')
                ->get(['team_id', 'position']);

            return $placeholders->map(fn ($s) => [
                'teamId' => $s->team_id,
                'position' => (int) $s->position,
            ])->all();
        }

        $results = $this->reconcileWithEntries($game, $competitionId, $simulated);

        $out = [];
        foreach ($results as $index => $teamId) {
            $out[] = ['teamId' => $teamId, 'position' => $index + 1];
        }

        return $out;
    }

    /**
     * Reconcile SimulatedSeason.results against current CompetitionEntry roster.
     * Returns the (possibly rewritten) results array. Mutates the SimulatedSeason
     * row if reconciliation is applied so subsequent reads see the same data.
     *
     * @return list<string>
     */
    private function reconcileWithEntries(Game $game, string $competitionId, SimulatedSeason $simulated): array
    {
        $simTeams = is_array($simulated->results) ? $simulated->results : (array) $simulated->results;

        $entryTeams = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->pluck('team_id')
            ->all();

        $stale = array_values(array_diff($simTeams, $entryTeams));
        $missing = array_values(array_diff($entryTeams, $simTeams));

        if (empty($stale) && empty($missing)) {
            return array_values($simTeams);
        }

        if (count($stale) !== count($missing)) {
            Log::warning('[StandingsReader] SimulatedSeason has unequal stale/missing sets; planner will see uncorrected data', [
                'game_id' => $game->id,
                'competition_id' => $competitionId,
                'stale' => $stale,
                'missing' => $missing,
            ]);

            return array_values($simTeams);
        }

        $replacement = array_combine($stale, $missing);
        $reconciled = array_map(
            fn ($teamId) => $replacement[$teamId] ?? $teamId,
            $simTeams,
        );

        $simulated->results = $reconciled;
        $simulated->save();

        Log::info('[StandingsReader] SimulatedSeason reconciled', [
            'game_id' => $game->id,
            'competition_id' => $competitionId,
            'replacements' => $replacement,
        ]);

        return array_values($reconciled);
    }
}
