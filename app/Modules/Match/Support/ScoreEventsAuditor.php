<?php

namespace App\Modules\Match\Support;

use App\Models\GameMatch;
use App\Models\MatchEvent;
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
        $eventCount = MatchEvent::where('game_match_id', $match->id)
            ->whereIn('event_type', ['goal', 'own_goal'])
            ->count();

        $expected = (int) $match->home_score + (int) $match->away_score
            + (int) ($match->home_score_et ?? 0) + (int) ($match->away_score_et ?? 0);

        if ($eventCount !== $expected) {
            Log::warning('Match score/events mismatch', [
                'stage' => $stage,
                'match_id' => $match->id,
                'goal_events' => $eventCount,
                'expected_total' => $expected,
                'home_score' => $match->home_score,
                'away_score' => $match->away_score,
                'home_score_et' => $match->home_score_et,
                'away_score_et' => $match->away_score_et,
            ]);
        }
    }
}
