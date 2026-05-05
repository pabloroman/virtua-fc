<?php

namespace App\Modules\Match\DTOs;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use Carbon\Carbon;

/**
 * Carries the data needed to dispatch post-finalize side effects after the
 * caller's lock-protected transaction has committed. Returned by
 * MatchFinalizationService::finalize() and consumed by
 * MatchFinalizationService::dispatchPostFinalizeEffects().
 */
readonly class MatchFinalizationResult
{
    public function __construct(
        public GameMatch $match,
        public Game $game,
        public ?Competition $competition,
        public Carbon $previousDate,
        public ?CupTie $resolvedCupTie = null,
        public ?string $cupTieWinnerId = null,
    ) {}
}
