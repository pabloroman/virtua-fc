<?php

namespace App\Http\Actions;

use App\Models\GameNotification;
use Illuminate\Http\Request;

class MarkNotificationRead
{
    public function __invoke(Request $request, string $gameId, string $notificationId)
    {
        $notification = GameNotification::where('game_id', $gameId)
            ->find($notificationId);

        if (! $notification) {
            return redirect()->route('show-game', $gameId);
        }

        $notification->markAsRead();

        // Redirect to the appropriate page based on notification type
        return redirect()->route(
            $notification->getNavigationRoute(),
            $notification->getNavigationParams($gameId),
        );
    }
}
