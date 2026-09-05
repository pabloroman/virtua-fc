<?php

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Events\CupTieResolved;
use App\Modules\Competition\Services\LeagueFixtureGenerator;
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

        // Prize tables are written from the final backwards, because a round
        // number means nothing on its own: round 5 is the Copa's quarter-final
        // and the Champions League final. Count back from the last round the
        // cup's schedule declares instead.
        $finalRound = LeagueFixtureGenerator::finalKnockoutRound($competition->id, $event->game->base_season);
        if ($finalRound === null) {
            return;
        }

        $roundsFromFinal = $finalRound - $event->cupTie->round_number;

        $amount = $competition->getConfig()->getKnockoutPrizeMoney($roundsFromFinal);

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
