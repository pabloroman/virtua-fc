<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GameNotification;

class ShowTransferActivity
{
    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Find the most recent AI transfer activity notification
        $notification = GameNotification::where('game_id', $game->id)
            ->where('type', GameNotification::TYPE_AI_TRANSFER_ACTIVITY)
            ->orderByDesc('game_date')
            ->first();

        if (! $notification) {
            return redirect()->route('show-game', $gameId);
        }

        // Mark the notification as read
        $notification->markAsRead();

        $metadata = $notification->metadata ?? [];
        $transfers = $metadata['transfers'] ?? [];
        $freeAgentSignings = $metadata['free_agent_signings'] ?? [];
        $window = $metadata['window'] ?? 'summer';

        return view('transfer-activity', [
            'game' => $game,
            'transfers' => $transfers,
            'freeAgentSignings' => $freeAgentSignings,
            'window' => $window,
        ]);
    }
}
