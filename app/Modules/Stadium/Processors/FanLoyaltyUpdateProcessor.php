<?php

namespace App\Modules\Stadium\Processors;

use App\Models\Game;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Stadium\Services\FanLoyaltyService;

/**
 * Nudges every team's loyalty_points based on the just-finished season's
 * outcomes.
 *
 * Ordering contract within SeasonClosingPipeline:
 *  - SeasonSettlementProcessor (priority 60) runs *before* this and computes
 *    actual matchday revenue from the season's recorded MatchAttendance rows,
 *    which were themselves driven by the *current-season* loyalty values.
 *  - ReputationUpdateProcessor (priority 90) runs *before* this so reputation
 *    deltas land first (useful context if later phases make loyalty deltas
 *    sensitive to reputation movement).
 *  - This processor (priority 92) then nudges loyalty_points for the *next*
 *    season's seeding, projection, and demand curve.
 */
class FanLoyaltyUpdateProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly FanLoyaltyService $fanLoyaltyService,
    ) {}

    public function priority(): int
    {
        return 92;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $this->fanLoyaltyService->applySeasonEndUpdate($game);

        return $data;
    }
}
