<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Transfer\Services\ScoutingService;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Http\Request;

class SignFreeAgent
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
        private readonly TransferService $transferService,
        private readonly NotificationService $notificationService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        $player = GamePlayer::where('game_id', $gameId)->with('player')->findOrFail($playerId);

        // Must be a free agent
        if ($player->team_id !== null) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('messages.not_free_agent'));
        }

        // Reputation gate: free agent must be willing to join
        if (! $this->scoutingService->canSignFreeAgent($player, $game->id, $game->team_id)) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('messages.free_agent_reputation_too_low'));
        }

        $wageDemand = $this->scoutingService->calculateWageDemand($player);

        $offer = $this->transferService->signFreeAgent($game, $player, $wageDemand);
        $this->notificationService->notifyTransferComplete($game, $offer);

        return redirect()->route('game.transfers', $gameId)
            ->with('success', __('messages.free_agent_signed', ['player' => $player->name]));
    }
}
