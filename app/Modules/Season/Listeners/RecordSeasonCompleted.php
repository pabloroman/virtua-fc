<?php

namespace App\Modules\Season\Listeners;

use App\Events\SeasonCompleted;
use App\Models\ActivationEvent;
use App\Modules\Season\Services\ActivationTracker;

class RecordSeasonCompleted
{
    public function __construct(
        private readonly ActivationTracker $activationTracker,
    ) {}

    public function handle(SeasonCompleted $event): void
    {
        $game = $event->game;

        $this->activationTracker->record(
            $game->user_id,
            ActivationEvent::EVENT_SEASON_COMPLETED,
            $game->id,
            $game->game_mode,
        );
    }
}
