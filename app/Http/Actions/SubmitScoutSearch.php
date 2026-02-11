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

        // Check no search currently in progress
        $searching = $this->scoutingService->getActiveReport($game);
        if ($searching) {
            return redirect()->route('game.scouting', $gameId)
                ->with('error', __('messages.scout_already_searching'));
        }

        $validated = $request->validate([
            'position' => 'required|string',
            'scope' => 'nullable|array',
            'scope.*' => 'in:domestic,international',
            'age_min' => 'nullable|integer|min:16|max:45',
            'age_max' => 'nullable|integer|min:16|max:45',
            'ability_min' => 'nullable|integer|min:1|max:99',
            'ability_max' => 'nullable|integer|min:1|max:99',
            'value_min' => 'nullable|integer|min:0',
            'value_max' => 'nullable|integer|min:0',
            'expiring_contract' => 'nullable|boolean',
        ]);

        $filters = [
            'position' => $validated['position'],
            'scope' => $validated['scope'] ?? ['domestic', 'international'],
            'age_min' => $validated['age_min'] ?? null,
            'age_max' => $validated['age_max'] ?? null,
            'ability_min' => $validated['ability_min'] ?? null,
            'ability_max' => $validated['ability_max'] ?? null,
            'value_min' => $validated['value_min'] ?? null,
            'value_max' => $validated['value_max'] ?? null,
            'expiring_contract' => !empty($validated['expiring_contract']),
        ];

        $this->scoutingService->startSearch($game, $filters);

        return redirect()->route('game.scouting', $gameId)
            ->with('success', __('messages.scout_search_started'));
    }
}
