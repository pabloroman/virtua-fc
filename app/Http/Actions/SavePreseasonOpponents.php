<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Season\Services\PreseasonOpponentService;
use Illuminate\Http\Request;

class SavePreseasonOpponents
{
    public function __construct(
        private readonly PreseasonOpponentService $opponentService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        if (! $game->needsPreseasonOpponentSelection()) {
            return redirect()->route('show-game', $gameId);
        }

        $validated = $request->validate([
            'slots' => ['array'],
            'slots.*.team_id' => ['nullable', 'string'],
            'slots.*.is_home' => ['nullable', 'boolean'],
        ]);

        // Reshape the per-slot form input into the service's selection list,
        // dropping slots the player left empty. The service does the real
        // validation (valid pool members, unique slots/teams).
        $selections = [];
        foreach ($validated['slots'] ?? [] as $slot => $input) {
            if (empty($input['team_id'])) {
                continue;
            }

            $selections[] = [
                'slot' => (int) $slot,
                'team_id' => $input['team_id'],
                'is_home' => filter_var($input['is_home'] ?? true, FILTER_VALIDATE_BOOL),
            ];
        }

        $this->opponentService->confirmSelections($game, $selections);

        return redirect()->route('show-game', $gameId)
            ->with('info', __('game.pre_season_ready'));
    }
}
