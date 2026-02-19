<?php

namespace App\Modules\Academy\Listeners;

use App\Events\SeasonStarted;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Academy\Services\YouthAcademyService;

class GenerateInitialAcademyBatch
{
    public function __construct(
        private readonly YouthAcademyService $youthAcademyService,
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(SeasonStarted $event): void
    {
        $game = $event->game;

        $batch = $this->youthAcademyService->generateSeasonBatch($game);

        if ($batch->isNotEmpty()) {
            $this->notificationService->notifyAcademyBatch($game, $batch->count());
        }
    }
}
