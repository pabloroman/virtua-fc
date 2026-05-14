<?php

namespace App\Http\Actions;

use App\Http\Actions\Concerns\HandlesStadiumProjectCommit;
use App\Models\Game;
use App\Modules\Stadium\Enums\StadiumProjectFinancing;
use App\Modules\Stadium\Services\StadiumUpgradeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommitStandExpansion
{
    use HandlesStadiumProjectCommit;

    public function __construct(
        private readonly StadiumUpgradeService $stadiumUpgradeService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        $validated = $request->validate([
            'seats' => 'required|integer|min:1',
            'financing' => ['required', Rule::enum(StadiumProjectFinancing::class)],
        ]);

        $seats = (int) $validated['seats'];
        $financing = StadiumProjectFinancing::from($validated['financing']);

        if ($redirect = $this->safeCommit($gameId, fn () => $this->stadiumUpgradeService->commitStandExpansion($game, $seats, $financing))) {
            return $redirect;
        }

        return $this->stadiumSuccess($gameId, 'messages.stadium_stand_expansion_committed', [
            'seats' => number_format($seats, 0, ',', '.'),
        ]);
    }
}
