<?php

namespace App\Http\Actions;

use App\Modules\Transfer\Services\TransferService;
use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UnlistPlayerFromTransfer
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

        $this->transferService->unlistPlayer($player);

        return redirect()
            ->route('game.transfers.outgoing', $gameId)
            ->with('success', __('messages.player_unlisted', ['player' => $player->player->name]));
    }
}
