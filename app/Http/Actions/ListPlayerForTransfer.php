<?php

namespace App\Http\Actions;

use App\Game\Services\TransferService;
use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ListPlayerForTransfer
{
    public function __construct(
        private readonly TransferService $transferService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId): RedirectResponse
    {
        $game = Game::findOrFail($gameId);
        $player = GamePlayer::where('id', $playerId)
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->firstOrFail();

        // List the player
        $this->transferService->listPlayer($player);

        return redirect()
            ->route('game.transfers', $gameId)
            ->with('success', __('messages.player_listed', ['player' => $player->player->name]));
    }
}
