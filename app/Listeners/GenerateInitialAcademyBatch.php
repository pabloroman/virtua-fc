<?php

namespace App\Listeners;

use App\Events\SeasonStarted;
use App\Game\Services\NotificationService;
use App\Game\Services\YouthAcademyService;

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
