<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Modules\ReserveTeam\Services\ReserveTeamService;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;

/**
 * For filial games, permanently promotes any reserve player who has aged
 * past the reserve cutoff (over 23) to the first team.
 *
 * Runs before LoanReturnProcessor (priority 5) so age-23 players are settled
 * permanently in the first team before remaining call-up loans snap back.
 *
 * No-op for non-filial games.
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
        if ($game->reserve_team_id === null) {
            return $data;
        }

        $promoted = $this->reserveTeamService->autoPromoteOverageReservePlayers($game);

        if ($promoted->isNotEmpty()) {
            $data->setMetadata('reserve_overage_promoted', $promoted->count());
        }

        return $data;
    }
}
