<?php

namespace App\Modules\Match\DTOs;

use Illuminate\Support\Collection;

/**
 * Output of a single MatchSimulator::simulateWindow() call.
 *
 * Carries the events generated during the window and the score/xG produced
 * for that window only (deltas, not cumulative totals). Cumulative state
 * lives on the owning MatchSimulationContext.
 */
readonly class WindowResult
{
    /**
     * @param  Collection<int, MatchEventData>  $newEvents
     */
    public function __construct(
        public Collection $newEvents,
        public int $homeScoreDelta,
        public int $awayScoreDelta,
        public float $homeXG,
        public float $awayXG,
        public int $homePossession,
        public int $awayPossession,
        public int $fromMinute,
        public int $toMinute,
    ) {}
}
