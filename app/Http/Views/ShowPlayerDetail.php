<?php

namespace App\Http\Views;

use App\Modules\Transfer\Services\ContractService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\RenewalNegotiation;

class ShowPlayerDetail
{
    public function __construct(
        private readonly ContractService $contractService,
    ) {}

    public function __invoke(string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);

        $gamePlayer = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->findOrFail($playerId);

        $canRenew = $gamePlayer->canBeOfferedRenewal();
        $renewalNegotiation = null;
        $renewalDemand = null;
        $renewalMidpoint = null;
        $renewalMood = null;

        if ($game->isCareerMode() && $gamePlayer->isContractExpiring()) {
            $renewalNegotiation = RenewalNegotiation::where('game_player_id', $gamePlayer->id)
                ->whereIn('status', [
                    RenewalNegotiation::STATUS_OFFER_PENDING,
                    RenewalNegotiation::STATUS_PLAYER_COUNTERED,
                ])
                ->first();

            if ($canRenew) {
                $renewalDemand = $this->contractService->calculateRenewalDemand($gamePlayer);
                $renewalMidpoint = (int) (ceil(($gamePlayer->annual_wage + $renewalDemand['wage']) / 2 / 100 / 10000) * 10000);
                $disposition = $this->contractService->calculateDisposition($gamePlayer);
                $renewalMood = $this->contractService->getMoodIndicator($disposition);
            }
        }

        return view('partials.player-detail', [
            'game' => $game,
            'gamePlayer' => $gamePlayer,
            'canRenew' => $canRenew,
            'renewalNegotiation' => $renewalNegotiation,
            'renewalDemand' => $renewalDemand,
            'renewalMidpoint' => $renewalMidpoint,
            'renewalMood' => $renewalMood,
        ]);
    }
}
