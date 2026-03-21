<?php

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Events\CupTieResolved;
use App\Modules\Competition\Services\CupDrawService;

class ConductNextCupRoundDraw
{
    public function __construct(
        private readonly CupDrawService $cupDrawService,
    ) {}

    public function handle(CupTieResolved $event): void
    {
        // Swiss format and group stage cup competitions handle their own knockout
        // generation via their respective handlers. CupDrawService uses generic
        // "winners from previous round" logic that breaks for special rounds like
        // the third-place match (which needs losers, not winners).
        if (in_array($event->competition?->handler_type, ['swiss_format', 'group_stage_cup'])) {
            return;
        }

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
