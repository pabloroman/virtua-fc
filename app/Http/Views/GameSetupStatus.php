<?php

namespace App\Http\Views;

use App\Models\Game;
use Illuminate\Http\JsonResponse;

class GameSetupStatus
{
    public function __invoke(string $gameId): JsonResponse
    {
        $game = Game::findOrFail($gameId);

        return response()->json([
            'ready' => $game->isSetupComplete(),
        ]);
    }
}
