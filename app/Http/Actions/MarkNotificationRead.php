<?php

namespace App\Http\Actions;

use App\Game\Services\NotificationService;
use App\Models\GameNotification;
use Illuminate\Http\Request;

class MarkNotificationRead
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $notificationId)
    {
        $notification = $this->notificationService->markAsRead($notificationId);

        if (!$notification) {
            return redirect()->route('show-game', $gameId);
        }

        // Redirect to the appropriate page based on notification type
        return redirect()->route($notification->getNavigationRoute(), $gameId);
    }
}
