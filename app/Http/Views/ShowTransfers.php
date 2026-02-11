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

        // Separate by offer type (exclude pre-contract offers - those are on the contracts page)
        $unsolicitedOffers = $pendingOffers->where('offer_type', TransferOffer::TYPE_UNSOLICITED);
        $listedOffers = $pendingOffers->where('offer_type', TransferOffer::TYPE_LISTED);

        // Get agreed transfers (waiting for window) - exclude pre-contracts
        $agreedTransfers = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('offer_type', '!=', TransferOffer::TYPE_PRE_CONTRACT)
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
            ->where('direction', '!=', TransferOffer::DIRECTION_INCOMING)
            ->orderByDesc('resolved_at')
            ->limit(10)
            ->get();

        // Get incoming agreed transfers (user buying/loaning players)
        $incomingAgreedTransfers = TransferOffer::with(['gamePlayer.player', 'gamePlayer.team', 'sellingTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->orderByDesc('game_date')
            ->get();

        // Get pending bids (user's offers awaiting response)
        $pendingBids = TransferOffer::with(['gamePlayer.player', 'gamePlayer.team', 'sellingTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->orderByDesc('game_date')
            ->get();

        // Get rejected bids (user's offers that were declined - show for 7 days)
        $rejectedBids = TransferOffer::with(['gamePlayer.player', 'gamePlayer.team', 'sellingTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_REJECTED)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->where('resolved_at', '>=', $game->current_date->subDays(7))
            ->orderByDesc('resolved_at')
            ->get();

        // Get transfer window info from Game model
        $isTransferWindow = $game->isTransferWindowOpen();
        $currentWindow = $game->getCurrentWindowName();

        return view('transfers', [
            'game' => $game,
            'unsolicitedOffers' => $unsolicitedOffers,
            'listedOffers' => $listedOffers,
            'agreedTransfers' => $agreedTransfers,
            'incomingAgreedTransfers' => $incomingAgreedTransfers,
            'pendingBids' => $pendingBids,
            'rejectedBids' => $rejectedBids,
            'listedPlayers' => $listedPlayers,
            'recentTransfers' => $recentTransfers,
            'currentWindow' => $currentWindow,
            'isTransferWindow' => $isTransferWindow,
        ]);
    }
}
