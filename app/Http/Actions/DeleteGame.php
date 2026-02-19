<?php

namespace App\Http\Actions;

use App\Modules\Season\Services\GameDeletionService;
use App\Models\Game;
use Illuminate\Http\Request;

class DeleteGame
{
    public function __invoke(Request $request, string $gameId, GameDeletionService $service)
    {
        $game = Game::findOrFail($gameId);

        if ($game->user_id !== $request->user()->id) {
            abort(403);
        }

        $service->delete($game);

        return redirect()->route('dashboard')->with('success', __('messages.game_deleted'));
    }
}
