<?php

namespace App\Http\Actions;

use App\Game\Services\ScoutingService;
use App\Models\Game;
use Illuminate\Http\Request;

class SubmitScoutSearch
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // Check no active search exists
        $existing = $this->scoutingService->getActiveReport($game);
        if ($existing) {
            return redirect()->route('game.scouting', $gameId)
                ->with('error', __('messages.scout_already_searching'));
        }

        $validated = $request->validate([
            'position' => 'required|string',
            'league' => 'nullable|string',
            'age_min' => 'nullable|integer|min:16|max:45',
            'age_max' => 'nullable|integer|min:16|max:45',
            'max_budget' => 'nullable|numeric|min:0',
        ]);

        $filters = [
            'position' => $validated['position'],
            'league' => $validated['league'] ?? 'all',
            'age_min' => $validated['age_min'] ?? null,
            'age_max' => $validated['age_max'] ?? null,
            'max_budget' => $validated['max_budget'] ?? null,
        ];

        $this->scoutingService->startSearch($game, $filters);

        return redirect()->route('game.scouting', $gameId)
            ->with('success', __('messages.scout_search_started'));
    }
}
