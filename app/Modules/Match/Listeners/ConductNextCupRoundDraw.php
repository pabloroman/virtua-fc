<?php

namespace App\Modules\Match\Listeners;

use App\Modules\Competition\Exceptions\OddCupDrawPoolException;
use App\Modules\Competition\Services\CupDrawRevealService;
use App\Modules\Competition\Services\CupDrawService;
use App\Modules\Match\Events\CupTieResolved;
use Illuminate\Support\Facades\Log;

class ConductNextCupRoundDraw
{
    public function __construct(
        private readonly CupDrawService $cupDrawService,
        private readonly CupDrawRevealService $drawReveal,
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

        if ($nextRound === null) {
            return;
        }

        try {
            $this->cupDrawService->conductDraw(
                $event->game->id,
                $event->match->competition_id,
                $nextRound,
            );

            // Inside the try on purpose: an abandoned draw must not queue a
            // ceremony for a bracket that was never completed.
            $this->drawReveal->record($event->game, $event->match->competition_id, $nextRound);
        } catch (OddCupDrawPoolException $e) {
            // Legacy games whose brackets were corrupted by the pre-fix
            // truncation (see commit b644bc961) can hit this on every
            // post-fix draw. Letting the throw propagate blocks match
            // finalization and strands the user. Swallow it instead so
            // the cup quietly stops drawing further rounds — the supercup
            // and UEFA cup-winner downstream paths already handle the
            // "no completed final" case (supercup falls back to league
            // top 4; UEFA cup slot cascades to next league position).
            // Report so the error tracker sees how often this fires
            // post-fix; the new prevention guards mean it should be rare.
            report($e);
            Log::error('Cup abandoned mid-season due to odd draw pool', [
                'game_id' => $event->game->id,
                'competition_id' => $event->match->competition_id,
                'round' => $nextRound,
            ]);
        }
    }
}
