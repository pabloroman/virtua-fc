<?php

namespace App\Http\Actions;

use App\Http\Actions\Concerns\HandlesStadiumProjectCommit;
use App\Models\Game;
use App\Modules\Stadium\Services\StadiumUpgradeService;
use Illuminate\Http\Request;

class CommitSupplementaryStands
{
    use HandlesStadiumProjectCommit;

    public function __construct(
        private readonly StadiumUpgradeService $stadiumUpgradeService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        $validated = $request->validate(['seats' => 'required|integer|min:1']);

        if ($redirect = $this->safeCommit($gameId, fn () => $this->stadiumUpgradeService->commitSupplementary($game, (int) $validated['seats']))) {
            return $redirect;
        }

        return $this->stadiumSuccess($gameId, 'messages.stadium_supplementary_committed', [
            'seats' => number_format((int) $validated['seats'], 0, ',', '.'),
        ]);
    }
}
