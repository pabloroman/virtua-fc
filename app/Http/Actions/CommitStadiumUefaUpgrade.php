<?php

namespace App\Http\Actions;

use App\Http\Actions\Concerns\HandlesStadiumProjectCommit;
use App\Models\Game;
use App\Models\GameStadiumProject;
use App\Modules\Stadium\Enums\StadiumProjectFinancing;
use App\Modules\Stadium\Services\StadiumUpgradeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommitStadiumUefaUpgrade
{
    use HandlesStadiumProjectCommit;

    public function __construct(
        private readonly StadiumUpgradeService $stadiumUpgradeService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        $validated = $request->validate([
            'financing' => ['required', Rule::enum(StadiumProjectFinancing::class)],
        ]);

        $financing = StadiumProjectFinancing::from($validated['financing']);
        $project = null;

        $redirect = $this->safeCommit($gameId, function () use ($game, $financing, &$project) {
            $project = $this->stadiumUpgradeService->commitUefaUpgrade($game, $financing);
        });

        if ($redirect) {
            return $redirect;
        }

        /** @var GameStadiumProject $project */
        return $this->stadiumSuccess($gameId, 'messages.stadium_uefa_upgrade_committed', [
            'level' => (int) $project->target_capacity,
        ]);
    }
}
