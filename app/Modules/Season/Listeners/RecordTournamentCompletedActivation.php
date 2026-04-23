<?php

namespace App\Modules\Season\Listeners;

use App\Events\TournamentEnded;
use App\Models\ActivationEvent;
use App\Models\Game;
use App\Modules\Season\Services\ActivationTracker;

class RecordTournamentCompletedActivation
{
    public function __construct(
        private readonly ActivationTracker $activationTracker,
    ) {}

    public function handle(TournamentEnded $event): void
    {
        $game = $event->game;

        $this->activationTracker->record(
            $game->user_id,
            ActivationEvent::EVENT_TOURNAMENT_COMPLETED,
            $game->id,
            Game::MODE_TOURNAMENT,
        );
    }
}
