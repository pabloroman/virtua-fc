<?php

namespace App\Game\Listeners;

use App\Game\Events\CupTieResolved;
use App\Game\Services\CupDrawService;

class ConductNextCupRoundDraw
{
    public function __construct(
        private readonly CupDrawService $cupDrawService,
    ) {}

    public function handle(CupTieResolved $event): void
    {
        $nextRound = $this->cupDrawService->getNextRoundNeedingDraw(
            $event->game->id,
            $event->match->competition_id,
        );

        if ($nextRound !== null) {
            $this->cupDrawService->conductDraw(
                $event->game->id,
                $event->match->competition_id,
                $nextRound,
            );
        }
    }
}
