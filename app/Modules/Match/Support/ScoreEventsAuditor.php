<?php

namespace App\Modules\Match\Support;

use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Modules\Match\Enums\MatchPhase;
use Illuminate\Support\Facades\Log;

/**
 * Verify that the count of persisted goal events matches the persisted
 * scoreboard for a match.
 *
 * The simulator derives score and events from the same Poisson roll, but in
 * resimulation paths the two are stored and updated independently. A
 * mismatch — events without a corresponding score increment, or vice versa —
 * surfaces as a "phantom goal" in the UI. We don't throw (a user mid-match
 * shouldn't see a 500), but we do log loudly so operations can investigate.
 */
final class ScoreEventsAuditor
{
    public static function audit(GameMatch $match, string $stage): void
    {
        $regulationPhases = array_map(fn (MatchPhase $p) => $p->value, MatchPhase::regulation());

        $totalEventCount = MatchEvent::where('game_match_id', $match->id)
            ->whereIn('event_type', ['goal', 'own_goal'])
            ->count();

        $regulationEventCount = MatchEvent::where('game_match_id', $match->id)
            ->whereIn('event_type', ['goal', 'own_goal'])
            ->whereIn('phase', $regulationPhases)
            ->count();

        $expectedTotal = (int) $match->home_score + (int) $match->away_score
            + (int) ($match->home_score_et ?? 0) + (int) ($match->away_score_et ?? 0);

        $expectedRegulation = (int) $match->home_score + (int) $match->away_score;

        if ($totalEventCount !== $expectedTotal) {
            Log::warning('Match score/events mismatch', [
                'stage' => $stage,
                'match_id' => $match->id,
                'goal_events' => $totalEventCount,
                'expected_total' => $expectedTotal,
                'home_score' => $match->home_score,
                'away_score' => $match->away_score,
                'home_score_et' => $match->home_score_et,
                'away_score_et' => $match->away_score_et,
            ]);
        }

        // Regulation-only check: catches drift between the regulation
        // scoreboard and regulation-phase events even when the total
        // happens to balance because of an offsetting mistake in ET.
        // This is the regression check requested by issue #1158.
        if ($regulationEventCount !== $expectedRegulation) {
            Log::warning('Match regulation score/events mismatch', [
                'stage' => $stage,
                'match_id' => $match->id,
                'regulation_goal_events' => $regulationEventCount,
                'expected_regulation' => $expectedRegulation,
                'home_score' => $match->home_score,
                'away_score' => $match->away_score,
            ]);
        }
    }
}
