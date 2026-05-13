<?php

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Events\CupTieResolved;
use App\Models\FinancialTransaction;

class AwardCupPrizeMoney
{
    public function handle(CupTieResolved $event): void
    {
        if ($event->winnerId !== $event->game->team_id) {
            return;
        }

        $competition = $event->competition;
        if (!$competition) {
            return;
        }

        $amount = $competition->getConfig()->getKnockoutPrizeMoney($event->cupTie->round_number);

        if ($amount <= 0) {
            return;
        }

        $roundKey = $event->cupTie->firstLegMatch?->round_name;
        $roundLabel = $roundKey
            ? __($roundKey)
            : __('cup.round_n', ['round' => $event->cupTie->round_number]);

        FinancialTransaction::recordIncome(
            gameId: $event->game->id,
            category: FinancialTransaction::CATEGORY_CUP_BONUS,
            amount: $amount,
            description: __('finances.tx_cup_advancement', ['competition' => $competition->name, 'round' => $roundLabel]),
            transactionDate: $event->game->current_date->toDateString(),
        );
    }
}
