<?php

namespace App\Http\Actions;

use App\Models\Game;

class ExitFastMode
{
    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        if ($game->isFastMode()) {
            $game->update([
                'fast_mode' => false,
                'fast_mode_entered_on' => null,
            ]);
        }

        return redirect()->route('show-game', $gameId)
            ->with('info', __('messages.fast_mode_disabled'));
    }
}
