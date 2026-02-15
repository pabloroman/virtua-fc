<?php

namespace App\Http\Actions;

use App\Game\Services\GameCreationService;
use App\Models\Game;
use Illuminate\Http\Request;

class InitGame
{
    public function __construct(
        private readonly GameCreationService $gameCreationService,
    ) {}

    public function __invoke(Request $request)
    {
        $gameCount = Game::where('user_id', $request->user()->id)->count();
        if ($gameCount >= 3) {
            return back()->withErrors(['limit' => __('messages.game_limit_reached')]);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:25'],
            'team_id' => ['required', 'uuid'],
        ]);

        $game = $this->gameCreationService->create(
            userId: (string) $request->user()->id,
            playerName: $request->get('name'),
            teamId: $request->get('team_id'),
        );

        return redirect()->route('game.onboarding', $game->id);
    }
}
