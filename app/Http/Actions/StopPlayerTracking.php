<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\ShortlistedPlayer;
use App\Modules\Transfer\Services\ScoutingService;
use Illuminate\Http\Request;

class StopPlayerTracking
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);

        $entry = ShortlistedPlayer::where('game_id', $gameId)
            ->where('game_player_id', $playerId)
            ->firstOrFail();

        $this->scoutingService->stopTracking($entry);

        $capacity = $this->scoutingService->getTrackingCapacity($game);

        return response()->json([
            'success' => true,
            'message' => __('messages.tracking_stopped', ['player' => $entry->gamePlayer->name]),
            'trackingCapacity' => $capacity,
        ]);
    }
}
