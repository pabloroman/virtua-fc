<?php

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Stadium\Services\MatchAttendanceService;

/**
 * Safety-net listener that guarantees every finalized match has a
 * MatchAttendance row. MatchdayOrchestrator::processBatch is the primary
 * writer (called before simulation for every fixture in the batch), so
 * under normal flow this is a no-op — resolveForMatch short-circuits on
 * the existing row. It exists to cover any future path that finalizes a
 * match without having gone through the orchestrator batch.
 */
class EnsureMatchAttendance
{
    public function __construct(
        private readonly MatchAttendanceService $attendanceService,
    ) {}

    public function handle(MatchFinalized $event): void
    {
        $this->attendanceService->resolveForMatch($event->match, $event->game);
    }
}
