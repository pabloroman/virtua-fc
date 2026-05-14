<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Models\StadiumLoan;
use App\Modules\Finance\Services\StadiumLoanService;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Stadium\Enums\StadiumLoanStatus;

/**
 * Bills the annual instalment on every active stadium loan at season
 * close. Stadium project progression itself is calendar-based and runs
 * outside the season pipeline (ActivateCompletedStadiumProjects listener).
 *
 * Runs at priority 65 — immediately after SeasonSettlementProcessor (60),
 * which handles single-season budget-loan repayment. Stadium-loan
 * instalments are billed here so that BudgetProjectionProcessor in the
 * setup pipeline sees an up-to-date remaining_principal when projecting
 * next season's debt service.
 */
class StadiumLoanBillingProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly StadiumLoanService $loanService,
    ) {}

    public function priority(): int
    {
        return 65;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $loans = StadiumLoan::query()
            ->where('game_id', $game->id)
            ->where('status', StadiumLoanStatus::Active->value)
            ->get();

        foreach ($loans as $loan) {
            $this->loanService->billAnnualPayment($loan, $game);
        }

        return $data;
    }
}
