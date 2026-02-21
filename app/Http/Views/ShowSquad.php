<?php

namespace App\Http\Views;

use App\Modules\Transfer\Services\ContractService;
use App\Modules\Lineup\Services\LineupService;
use App\Modules\Squad\Services\DevelopmentCurve;
use App\Modules\Squad\Services\PlayerDevelopmentService;
use App\Models\AcademyPlayer;
use App\Models\Game;
use App\Models\TransferOffer;

class ShowSquad
{
    public function __construct(
        private readonly ContractService $contractService,
        private readonly LineupService $lineupService,
        private readonly PlayerDevelopmentService $developmentService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Get all players for the user's team, sorted by position group
        $allPlayers = $this->lineupService->getAllPlayers($gameId, $game->team_id)
            ->sortBy(fn ($p) => LineupService::positionSortOrder($p->position))
            ->values()
            ->map(function ($player) {
                $player->setAttribute('projection', $this->developmentService->getNextSeasonProjection($player));
                $player->setAttribute('development_status', DevelopmentCurve::getStatus($player->age));
                $player->setAttribute('goal_contributions', $player->goals + $player->assists);
                $player->setAttribute('goals_per_game', $player->appearances > 0
                    ? round($player->goals / $player->appearances, 2)
                    : 0);
                return $player;
            });

        // Career-mode only: contract and transfer data
        $renewalEligiblePlayers = collect();
        $renewalDemands = [];
        $pendingRenewals = collect();
        $preContractOffers = collect();
        $agreedPreContracts = collect();
        $isTransferWindow = false;
        $academyCount = 0;

        if ($game->isCareerMode()) {
            $renewalEligiblePlayers = $this->contractService->getPlayersEligibleForRenewal($game);
            foreach ($renewalEligiblePlayers as $player) {
                $renewalDemands[$player->id] = $this->contractService->calculateRenewalDemand($player);
            }

            $pendingRenewals = $this->contractService->getPlayersWithPendingRenewals($game);

            $preContractOffers = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
                ->where('game_id', $gameId)
                ->where('status', TransferOffer::STATUS_PENDING)
                ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
                ->whereHas('gamePlayer', function ($query) use ($game) {
                    $query->where('team_id', $game->team_id);
                })
                ->where('expires_at', '>=', $game->current_date)
                ->orderByDesc('game_date')
                ->get();

            $agreedPreContracts = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
                ->where('game_id', $gameId)
                ->where('status', TransferOffer::STATUS_AGREED)
                ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
                ->whereHas('gamePlayer', function ($query) use ($game) {
                    $query->where('team_id', $game->team_id);
                })
                ->get();

            $isTransferWindow = $game->isTransferWindowOpen();
            $academyCount = AcademyPlayer::where('game_id', $gameId)->where('team_id', $game->team_id)->count();
        }

        $seasonEndDate = $game->getSeasonEndDate();

        return view('squad', [
            'game' => $game,
            'players' => $allPlayers,
            'renewalEligiblePlayers' => $renewalEligiblePlayers,
            'renewalDemands' => $renewalDemands,
            'pendingRenewals' => $pendingRenewals,
            'preContractOffers' => $preContractOffers,
            'agreedPreContracts' => $agreedPreContracts,
            'isTransferWindow' => $isTransferWindow,
            'academyCount' => $academyCount,
            'seasonEndDate' => $seasonEndDate,
        ]);
    }
}
