<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Squad\Services\SquadService;

class ShowSquad
{
    public function __construct(
        private readonly SquadService $squadService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $data = $this->squadService->buildSquadOverview($game);

        return view('squad', ['game' => $game, ...$data]);
    }
}
