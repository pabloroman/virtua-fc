<?php

namespace App\Modules\Transfer\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferOffer;

class TransferHeaderService
{
    /**
     * Gather all shared header data for the transfer tab views.
     *
     * @return array{
     *     isTransferWindow: bool,
     *     currentWindow: string|null,
     *     windowCountdown: array|null,
     *     totalWageBill: int,
     *     salidaBadgeCount: int,
     * }
     */
    public function getHeaderData(Game $game): array
    {
        return [
            'isTransferWindow' => $game->isTransferWindowOpen(),
            'currentWindow' => $game->getCurrentWindowName(),
            'windowCountdown' => $game->getWindowCountdown(),
            'totalWageBill' => $this->getTotalWageBill($game),
            'salidaBadgeCount' => $this->getSalidaBadgeCount($game),
        ];
    }

    private function getTotalWageBill(Game $game): int
    {
        return (int) GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->sum('annual_wage');
    }

    private function getSalidaBadgeCount(Game $game): int
    {
        return TransferOffer::where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_PENDING)
            // Only offers from *other* clubs are departures. A player loaned in
            // to the user sits at the user's team_id, so a pre-contract the user
            // himself made for that player would otherwise inflate this badge.
            ->where('offering_team_id', '!=', $game->team_id)
            ->whereHas('gamePlayer', function ($query) use ($game) {
                $query->where('team_id', $game->team_id);
            })
            ->where('expires_at', '>=', $game->current_date)
            ->whereIn('offer_type', [
                TransferOffer::TYPE_UNSOLICITED,
                TransferOffer::TYPE_LISTED,
                TransferOffer::TYPE_PRE_CONTRACT,
            ])
            ->count();
    }

}
