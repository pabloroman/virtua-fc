<?php

namespace App\Modules\LiveMatch\Jobs;

use App\Models\LiveMatchSession;
use App\Modules\LiveMatch\Enums\LiveMatchPhase;
use App\Modules\LiveMatch\Services\LiveMatchOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Simulates a single ~10-minute window of a live match, then chains itself
 * for the next window (or yields to a pause-ack flow).
 *
 * Idempotency: the session's `clock_version` is bumped on disconnect /
 * resume / abort. Each job carries the version it was dispatched with and
 * exits early if it no longer matches — cheap protection against double
 * dispatch and races after reconnect.
 */
class AdvanceLiveMatchWindowJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $sessionId,
        public int $clockVersion,
        public int $fromMinute,
    ) {}

    public function handle(LiveMatchOrchestrator $orchestrator): void
    {
        $session = LiveMatchSession::find($this->sessionId);
        if ($session === null) {
            return;
        }
        if ($session->clock_version !== $this->clockVersion) {
            // Stale — another job (or a resume) has taken over.
            return;
        }
        if ($session->phase !== LiveMatchPhase::Live) {
            return;
        }

        $shouldContinue = $orchestrator->advanceWindow($session, $this->fromMinute);
        if (! $shouldContinue) {
            return;
        }

        $session->refresh();
        self::dispatch(
            $session->id,
            $session->clock_version,
            $session->current_minute,
        )->delay(now()->addSeconds(LiveMatchOrchestrator::WINDOW_DELAY_SECONDS));
    }
}
