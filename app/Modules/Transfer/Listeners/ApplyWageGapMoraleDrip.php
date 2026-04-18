<?php

namespace App\Modules\Transfer\Listeners;

use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Transfer\Services\DispositionService;

class ApplyWageGapMoraleDrip
{
    public function __construct(
        private readonly DispositionService $dispositionService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        // Fire once per in-game calendar month, on the tick that crosses
        // the boundary. Stateless: no persisted marker needed because
        // previousDate and newDate pin down whether the month changed.
        if ($event->previousDate->format('Y-m') === $event->newDate->format('Y-m')) {
            return;
        }

        $this->dispositionService->applyWageGapMoraleDrip($event->game);
    }
}
