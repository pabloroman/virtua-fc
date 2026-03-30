<?php

namespace App\Http\Actions;

use App\Modules\Transfer\Services\TransferService;
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

        if ($player->isLoanedIn($game->team_id)) {
            abort(403, 'Cannot list a loaned player for transfer.');
        }

        // List the player
        $this->transferService->listPlayer($player);

        return redirect()
            ->back()
            ->with('success', __('messages.player_listed', ['player' => $player->player->name]));
    }
}
