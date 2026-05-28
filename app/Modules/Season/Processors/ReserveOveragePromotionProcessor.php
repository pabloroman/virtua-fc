<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Models\Team;
use App\Modules\ReserveTeam\Services\ReserveTeamService;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;

/**
 * Permanently promotes any reserve player who has aged past the reserve
 * cutoff (over 23) to the first team. Applies to both the user's filial
 * (if any) and every AI parent club that has a reserve team — without this
 * the AI side accumulates over-age players on reserve rosters indefinitely.
 *
 * Runs before LoanReturnProcessor (priority 5) so age-23 players are settled
 * permanently in the first team before remaining call-up loans snap back.
 *
 * Priority: 4
 */
class ReserveOveragePromotionProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly ReserveTeamService $reserveTeamService,
    ) {}

    public function priority(): int
    {
        return 4;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $promoted = collect();

        if ($game->reserve_team_id !== null) {
            $promoted = $promoted->concat(
                $this->reserveTeamService->autoPromoteOverageReservePlayers($game)
            );
        }

        $userTeamIds = $game->userTeamIds();
        $aiReserves = Team::whereNotNull('parent_team_id')
            ->when(! empty($userTeamIds), fn ($q) => $q
                ->whereNotIn('id', $userTeamIds)
                ->whereNotIn('parent_team_id', $userTeamIds))
            ->get(['id', 'parent_team_id']);

        foreach ($aiReserves as $reserve) {
            $promoted = $promoted->concat(
                $this->reserveTeamService->autoPromoteOverageReservePlayers(
                    $game,
                    $reserve->id,
                    $reserve->parent_team_id,
                )
            );
        }

        if ($promoted->isNotEmpty()) {
            $data->setMetadata('reserve_overage_promoted', $promoted->count());
        }

        return $data;
    }
}
