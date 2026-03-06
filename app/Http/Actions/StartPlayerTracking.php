<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\ShortlistedPlayer;
use App\Modules\Transfer\Services\ScoutingService;
use Illuminate\Http\Request;

class StartPlayerTracking
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

        $started = $this->scoutingService->startTracking($entry, $game);

        if (! $started) {
            return response()->json([
                'success' => false,
                'message' => __('messages.tracking_slots_full'),
            ], 422);
        }

        $capacity = $this->scoutingService->getTrackingCapacity($game);

        return response()->json([
            'success' => true,
            'message' => __('messages.tracking_started', ['player' => $entry->gamePlayer->name]),
            'trackingCapacity' => $capacity,
        ]);
    }
}
