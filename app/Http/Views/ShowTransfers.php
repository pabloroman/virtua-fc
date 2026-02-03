<?php

namespace App\Http\Views;

use App\Game\Services\TransferService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferOffer;

class ShowTransfers
{
    public function __construct(
        private readonly TransferService $transferService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);

        // Expire any old offers first
        $this->transferService->expireOffers($game);

        // Get all pending offers for user's players
        $pendingOffers = TransferOffer::with(['gamePlayer.player', 'gamePlayer.team', 'offeringTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->whereHas('gamePlayer', function ($query) use ($game) {
                $query->where('team_id', $game->team_id);
            })
            ->where('expires_at', '>=', $game->current_date)
            ->orderByDesc('transfer_fee')
            ->get();

        // Separate by offer type
        $unsolicitedOffers = $pendingOffers->where('offer_type', TransferOffer::TYPE_UNSOLICITED);
        $listedOffers = $pendingOffers->where('offer_type', TransferOffer::TYPE_LISTED);

        // Get agreed transfers (waiting for window)
        $agreedTransfers = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->whereHas('gamePlayer', function ($query) use ($game) {
                $query->where('team_id', $game->team_id);
            })
            ->orderByDesc('transfer_fee')
            ->get();

        // Get listed players (even those without offers, excluding those with agreed deals)
        $agreedPlayerIds = $agreedTransfers->pluck('game_player_id')->toArray();
        $listedPlayers = GamePlayer::with(['player', 'activeOffers.offeringTeam'])
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->where('transfer_status', GamePlayer::TRANSFER_STATUS_LISTED)
            ->whereNotIn('id', $agreedPlayerIds)
            ->orderByDesc('market_value_cents')
            ->get();

        // Recent completed transfers (last 10)
        $recentTransfers = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_COMPLETED)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        // Get next transfer window info
        $nextWindow = $this->transferService->getNextTransferWindow($game);
        $isTransferWindow = $this->transferService->isTransferWindow($game);

        return view('transfers', [
            'game' => $game,
            'pendingOffers' => $pendingOffers,
            'unsolicitedOffers' => $unsolicitedOffers,
            'listedOffers' => $listedOffers,
            'agreedTransfers' => $agreedTransfers,
            'listedPlayers' => $listedPlayers,
            'recentTransfers' => $recentTransfers,
            'nextWindow' => $nextWindow,
            'isTransferWindow' => $isTransferWindow,
        ]);
    }
}
