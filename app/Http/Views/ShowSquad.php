<?php

namespace App\Http\Views;

use App\Modules\Squad\Services\DevelopmentCurve;
use App\Modules\Squad\Services\PlayerDevelopmentService;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Lineup\Services\LineupService;
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

        // Get all players as flat collection + grouped for summary counts
        $positionGroups = $this->lineupService->getPlayersByPositionGroup($gameId, $game->team_id);

        // Enrich players with development + stats data
        $players = $positionGroups['all']
            ->map(function ($player) {
                // Development data
                $player->setAttribute('projection', $this->developmentService->getNextSeasonProjection($player));
                $player->setAttribute('development_status', DevelopmentCurve::getStatus($player->age));

                // Stats data
                $player->setAttribute('goal_contributions', $player->goals + $player->assists);
                $player->setAttribute('goals_per_game', $player->appearances > 0
                    ? round($player->goals / $player->appearances, 2)
                    : 0);

                return $player;
            })
            ->sortBy(fn ($p) => LineupService::positionSortOrder($p->position));

        // Squad totals for stats summary
        $totals = [
            'appearances' => $players->sum('appearances'),
            'goals' => $players->sum('goals'),
            'assists' => $players->sum('assists'),
            'own_goals' => $players->sum('own_goals'),
            'yellow_cards' => $players->sum('yellow_cards'),
            'red_cards' => $players->sum('red_cards'),
            'clean_sheets' => $players->where('position', 'Goalkeeper')->sum('clean_sheets'),
        ];

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
            'players' => $players,
            'goalkeepers' => $positionGroups['goalkeepers'],
            'defenders' => $positionGroups['defenders'],
            'midfielders' => $positionGroups['midfielders'],
            'forwards' => $positionGroups['forwards'],
            'totals' => $totals,
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
