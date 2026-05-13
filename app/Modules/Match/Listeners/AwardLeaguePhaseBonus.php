<?php

namespace App\Modules\Match\Listeners;

use App\Models\FinancialTransaction;
use App\Models\GameStanding;
use App\Modules\Match\Events\LeaguePhaseCompleted;

class AwardLeaguePhaseBonus
{
    public function handle(LeaguePhaseCompleted $event): void
    {
        $standing = GameStanding::where('game_id', $event->game->id)
            ->where('competition_id', $event->competition->id)
            ->where('team_id', $event->game->team_id)
            ->first();

        if (! $standing) {
            return;
        }

        $amount = $event->competition->getConfig()->getLeaguePhaseQualificationBonus($standing->position);

        if ($amount <= 0) {
            return;
        }

        FinancialTransaction::recordIncome(
            gameId: $event->game->id,
            category: FinancialTransaction::CATEGORY_CUP_BONUS,
            amount: $amount,
            description: __('finances.tx_league_phase_qualification', [
                'competition' => $event->competition->name,
                'position' => $standing->position,
            ]),
            transactionDate: $event->game->current_date->toDateString(),
        );
    }
}
