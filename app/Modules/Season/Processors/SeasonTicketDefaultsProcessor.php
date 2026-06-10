<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Modules\Finance\Services\BudgetProjectionService;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Stadium\Services\SeasonTicketPricingService;

/**
 * Seeds the user's season ticket pricing for the new season with the default
 * (Standard) preset derived from stadium capacity and reputation. Idempotent
 * — only writes when no row exists for the (game, season) so a manual override
 * applied mid-pipeline (e.g. during testing) is preserved.
 *
 * Runs late in the setup pipeline (after BudgetProjectionProcessor at 107) so
 * the GameFinances row already exists for the targeted ticketing refresh to
 * fold the default season-ticket + matchday revenue into the budget. The user
 * can then override on the Stadium page until the first competitive league
 * match has been played.
 */
class SeasonTicketDefaultsProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly SeasonTicketPricingService $pricingService,
        private readonly BudgetProjectionService $budgetProjectionService,
    ) {}

    public function priority(): int
    {
        return 115;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        if ($this->pricingService->applyDefaultIfMissing($game)) {
            $this->budgetProjectionService->refreshTicketingProjection($game);
        }

        return $data;
    }
}
