<?php

namespace App\Http\Actions;

use App\Http\Actions\Concerns\HandlesStadiumProjectCommit;
use App\Models\Game;
use App\Modules\Stadium\Services\NamingRightsService;
use Illuminate\Http\Request;

class RenameStadium
{
    use HandlesStadiumProjectCommit;

    public function __construct(
        private readonly NamingRightsService $namingRightsService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $validated = $request->validate([
            'name' => 'required|string|min:3|max:40',
        ]);

        $name = trim($validated['name']);

        if ($redirect = $this->safeCommit($gameId, fn () => $this->namingRightsService->rename($game, $name))) {
            return $redirect;
        }

        return $this->stadiumSuccess($gameId, 'messages.stadium_renamed', [
            'name' => $name,
        ]);
    }
}
