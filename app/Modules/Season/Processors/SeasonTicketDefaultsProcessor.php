<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Stadium\Services\SeasonTicketPricingService;

/**
 * Seeds the user's season ticket pricing for the new season with defaults
 * derived from stadium capacity and reputation. Idempotent — only writes
 * when no row exists for the (game, season) so a manual override applied
 * mid-pipeline (e.g. during testing) is preserved.
 *
 * Runs late in the setup pipeline (after BudgetProjectionProcessor at 107)
 * so the GameFinances row already exists for syncFinances() to update with
 * default season ticket revenue. The user can then override on the Stadium
 * page until the first competitive league match has been played.
 */
class SeasonTicketDefaultsProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly SeasonTicketPricingService $pricingService,
    ) {}

    public function priority(): int
    {
        return 115;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $this->pricingService->applyDefaultIfMissing($game);

        return $data;
    }
}
