<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Transfer\Services\ContractService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReleasePlayer
{
    public function __construct(
        private readonly ContractService $contractService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId): RedirectResponse
    {
        $game = Game::findOrFail($gameId);
        $player = GamePlayer::where('id', $playerId)
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->firstOrFail();

        $result = $this->contractService->releasePlayer($game, $player);

        if ($result['error'] ?? false) {
            return redirect()
                ->route('game.squad', $gameId)
                ->with('error', $result['error']);
        }

        // Auto-clear squad_trim pending action if squad is now within cap
        if ($game->hasPendingAction('squad_trim') && ContractService::squadCount($game) <= ContractService::MAX_SQUAD_SIZE) {
            $game->removePendingAction('squad_trim');
        }

        return redirect()
            ->route('game.squad', $gameId)
            ->with('success', __('messages.player_released', [
                'player' => $result['playerName'],
                'severance' => $result['formattedSeverance'],
            ]));
    }
}
