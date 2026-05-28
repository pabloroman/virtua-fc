<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Models\Team;
use App\Modules\ReserveTeam\Services\ReserveTeamService;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;

/**
 * After loans return at season close, AI parent clubs promote their own
 * top young reserve prospects to the first team. Permanent moves recorded
 * as TYPE_INTERNAL_PROMOTION. Without this, AI reserves stockpile their
 * best young talent indefinitely instead of feeding the senior squad.
 *
 * Priority: 6 — runs after ReserveOveragePromotionProcessor (4) has
 * already moved age-24+ players up, and after LoanReturnProcessor (5)
 * has returned any active call-up loans so the reserve roster is clean
 * before prospect ranking.
 */
class AIReserveCallUpProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly ReserveTeamService $reserveTeamService,
    ) {}

    public function priority(): int
    {
        return 6;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $promoted = collect();
        $userTeamIds = $game->userTeamIds();

        $aiReserves = Team::whereNotNull('parent_team_id')
            ->when(! empty($userTeamIds), fn ($q) => $q
                ->whereNotIn('id', $userTeamIds)
                ->whereNotIn('parent_team_id', $userTeamIds))
            ->get(['id', 'parent_team_id']);

        foreach ($aiReserves as $reserve) {
            $promoted = $promoted->concat(
                $this->reserveTeamService->autoPromoteAIReserveProspects(
                    $game,
                    $reserve->id,
                    $reserve->parent_team_id,
                )
            );
        }

        if ($promoted->isNotEmpty()) {
            $data->setMetadata('reserve_prospects_promoted', $promoted->count());
        }

        return $data;
    }
}
