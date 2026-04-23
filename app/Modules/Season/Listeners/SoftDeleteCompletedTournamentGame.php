<?php

namespace App\Modules\Season\Listeners;

use App\Events\TournamentEnded;
use App\Modules\Season\Services\GameDeletionService;

class SoftDeleteCompletedTournamentGame
{
    public function __construct(
        private readonly GameDeletionService $deletionService,
    ) {}

    public function handle(TournamentEnded $event): void
    {
        $this->deletionService->delete($event->game);
    }
}
