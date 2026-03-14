<?php

namespace App\Http\Actions;

use App\Models\ActivationEvent;
use App\Models\Game;
use App\Modules\Season\Services\ActivationTracker;

class CompleteWelcome
{
    public function __construct(
        private readonly ActivationTracker $activationTracker,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        if (!$game->needsWelcome()) {
            return redirect()->route('game.onboarding', $gameId);
        }

        $game->completeWelcome();

        $this->activationTracker->record($game->user_id, ActivationEvent::EVENT_WELCOME_COMPLETED, $gameId, $game->game_mode);

        return redirect()->route('game.onboarding', $gameId);
    }
}
