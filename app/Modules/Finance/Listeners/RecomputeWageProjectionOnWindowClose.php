<?php

namespace App\Modules\Finance\Listeners;

use App\Modules\Finance\Services\BudgetProjectionService;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Transfer\Enums\TransferWindowType;

/**
 * Restates the season wage projection the moment a transfer window closes.
 *
 * The season-start projection sums every squad member's annual wage once, in
 * pre-season, and never moves it — so after a window's ins and outs the
 * "projected salaries" line on the finances page stays frozen at its July value
 * (#1191 / #689). Recomputing once at window close (Sep 1 for summer, Feb 1 for
 * winter) folds the window's net squad change into the projection wholesale,
 * without per-transfer proration bookkeeping. The actual wage bill is still
 * reconciled independently at season-end settlement; this only keeps the
 * in-season forecast honest.
 */
class RecomputeWageProjectionOnWindowClose
{
    public function __construct(
        private readonly BudgetProjectionService $budgetProjectionService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        // Detect boundary crossing: previousDate was inside a window, newDate is
        // outside — mirrors ProcessTransferWindowClose so both fire on the same
        // matchday the window shuts.
        $previousWindow = TransferWindowType::fromDate($event->previousDate);
        $newWindow = TransferWindowType::fromDate($event->newDate);

        if (! $previousWindow || $newWindow !== null) {
            return;
        }

        $this->budgetProjectionService->recomputeWageProjection($event->game);
    }
}
