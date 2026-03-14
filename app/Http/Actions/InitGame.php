<?php

namespace App\Http\Actions;

use App\Models\ActivationEvent;
use App\Modules\Season\Services\ActivationTracker;
use App\Modules\Season\Services\GameCreationService;
use App\Modules\Season\Services\TournamentCreationService;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InitGame
{
    public function __construct(
        private readonly GameCreationService $gameCreationService,
        private readonly TournamentCreationService $tournamentCreationService,
        private readonly ActivationTracker $activationTracker,
    ) {}

    public function __invoke(Request $request)
    {
        $gameCount = Game::where('user_id', $request->user()->id)->count();
        if ($gameCount >= 3) {
            return back()->withErrors(['limit' => __('messages.game_limit_reached')]);
        }

        $request->validate([
            'team_id' => ['required', 'uuid'],
            'game_mode' => ['sometimes', Rule::in([Game::MODE_CAREER, Game::MODE_TOURNAMENT])],
        ]);

        $gameMode = $request->get('game_mode', Game::MODE_CAREER);

        if ($gameMode === Game::MODE_TOURNAMENT) {
            $game = $this->tournamentCreationService->create(
                userId: (string) $request->user()->id,
                teamId: $request->get('team_id'),
            );

            $this->activationTracker->record($request->user()->id, ActivationEvent::EVENT_GAME_CREATED, $game->id, Game::MODE_TOURNAMENT);

            return redirect()->route('show-game', $game->id);
        }

        $game = $this->gameCreationService->create(
            userId: (string) $request->user()->id,
            teamId: $request->get('team_id'),
            gameMode: $gameMode,
        );

        $this->activationTracker->record($request->user()->id, ActivationEvent::EVENT_GAME_CREATED, $game->id, $gameMode);

        return redirect()->route('game.welcome', $game->id);
    }
}
