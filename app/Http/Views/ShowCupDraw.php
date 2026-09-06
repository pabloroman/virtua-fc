<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Competition\Services\CupDrawRevealService;

final class ShowCupDraw
{
    public function __construct(
        private readonly CupDrawRevealService $drawReveal,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $payload = $this->drawReveal->build($game);

        // Nothing queued (or the queue held only stale entries, which build()
        // consumed) — the ceremony has nothing to show, so carry on.
        if ($payload === null) {
            return redirect()->route('show-game', $gameId);
        }

        return view('cup-draw', ['game' => $game] + $payload);
    }
}
