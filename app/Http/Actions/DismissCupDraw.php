<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Competition\Services\CupDrawRevealService;

final class DismissCupDraw
{
    public function __construct(
        private readonly CupDrawRevealService $drawReveal,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // Drops only the draw that was just watched. If another is queued behind
        // it, ShowGame's gate sends the user straight back into the ceremony.
        $this->drawReveal->dismiss($game);

        return redirect()->route('show-game', $gameId);
    }
}
