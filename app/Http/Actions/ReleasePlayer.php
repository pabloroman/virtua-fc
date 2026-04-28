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
            ->whereIn('team_id', $game->userTeamIds())
            ->firstOrFail();

        $wasOnReserve = $game->reserve_team_id !== null && $player->team_id === $game->reserve_team_id;
        $redirectRoute = $wasOnReserve ? 'game.squad.reserve' : 'game.squad';

        $result = $this->contractService->releasePlayer($game, $player);

        if ($result['error'] ?? false) {
            return redirect()
                ->route($redirectRoute, $gameId)
                ->with('error', $result['error']);
        }

        return redirect()
            ->route($redirectRoute, $gameId)
            ->with('success', __('messages.player_released', [
                'player' => $result['playerName'],
                'severance' => $result['formattedSeverance'],
            ]));
    }
}
