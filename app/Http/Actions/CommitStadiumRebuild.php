<?php

namespace App\Http\Actions;

use App\Http\Actions\Concerns\HandlesStadiumProjectCommit;
use App\Models\Game;
use App\Modules\Stadium\Enums\StadiumProjectFinancing;
use App\Modules\Stadium\Services\StadiumUpgradeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommitStadiumRebuild
{
    use HandlesStadiumProjectCommit;

    public function __construct(
        private readonly StadiumUpgradeService $stadiumUpgradeService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        $validated = $request->validate([
            'capacity' => 'required|integer|min:1',
            'financing' => ['required', Rule::enum(StadiumProjectFinancing::class)],
        ]);

        $capacity = (int) $validated['capacity'];
        $financing = StadiumProjectFinancing::from($validated['financing']);

        if ($redirect = $this->safeCommit($gameId, fn () => $this->stadiumUpgradeService->commitRebuild($game, $capacity, $financing))) {
            return $redirect;
        }

        return $this->stadiumSuccess($gameId, 'messages.stadium_rebuild_committed', [
            'capacity' => number_format($capacity, 0, ',', '.'),
        ]);
    }
}
