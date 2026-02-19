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
        // Swiss format competitions use SwissFormatHandler::maybeGenerateKnockoutRound
        // with seeded brackets. CupDrawService doesn't handle Swiss-specific entry
        // logic (top 8 entering directly at R16).
        if ($event->competition?->handler_type === 'swiss_format') {
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
