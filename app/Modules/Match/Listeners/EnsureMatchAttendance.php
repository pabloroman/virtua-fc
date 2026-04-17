<?php

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Stadium\Services\MatchAttendanceService;

/**
 * Safety-net listener that guarantees every finalized match has a
 * MatchAttendance row. The MatchdayOrchestrator pre-match hook normally
 * writes the row before simulation, so under typical flow this is a no-op
 * (the service short-circuits on existing rows). It exists to cover edge
 * paths like MatchFinalizationService::finalizePendingMatch where a match
 * may be finalized without going through the orchestrator batch.
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
