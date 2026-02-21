<?php

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Events\CupTieResolved;
use App\Models\FinancialTransaction;

class AwardCupPrizeMoney
{
    private const PRIZE_AMOUNTS = [
        1 => 10_000_000,
        2 => 20_000_000,
        3 => 30_000_000,
        4 => 50_000_000,
        5 => 100_000_000,
        6 => 200_000_000,
    ];

    public function handle(CupTieResolved $event): void
    {
        if ($event->winnerId !== $event->game->team_id) {
            return;
        }

        $roundNumber = $event->cupTie->round_number;
        $amount = self::PRIZE_AMOUNTS[$roundNumber] ?? self::PRIZE_AMOUNTS[1];
        $competitionName = $event->competition->name ?? 'Cup';

        FinancialTransaction::recordIncome(
            gameId: $event->game->id,
            category: FinancialTransaction::CATEGORY_CUP_BONUS,
            amount: $amount,
            description: __('finances.tx_cup_advancement', ['competition' => $competitionName, 'round' => $roundNumber]),
            transactionDate: $event->game->current_date->toDateString(),
        );
    }
}
