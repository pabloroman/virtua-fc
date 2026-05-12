<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Squad\Services\SquadPlannerService;

class ShowSquadPlanner
{
    public function __construct(
        private readonly SquadPlannerService $plannerService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Planner is a career-mode-only surface: contracts, retirements, and
        // pre-contracts (the signals it surfaces) are only meaningful there.
        abort_unless($game->isCareerMode(), 404);

        $payload = $this->plannerService->build($game);

        return view('squad-planner', [
            'game' => $game,
            'projection' => $payload['projection'],
            'advisories' => $payload['advisories'],
        ]);
    }
}
