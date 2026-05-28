<?php

namespace App\Modules\LiveMatch\Jobs;

use App\Models\LiveMatchSession;
use App\Modules\LiveMatch\Services\LiveMatchOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MarkAsBotJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $sessionId,
        public int $userId,
        public int $clockVersionAtDispatch,
    ) {}

    public function handle(LiveMatchOrchestrator $orchestrator): void
    {
        $session = LiveMatchSession::find($this->sessionId);
        if ($session === null) {
            return;
        }
        // Reconnect or any state change bumps clock_version — cancel the takeover.
        if ($session->clock_version !== $this->clockVersionAtDispatch) {
            return;
        }
        if ($session->phase->isTerminal()) {
            return;
        }

        $orchestrator->markAsBot($session, $this->userId);
    }
}
