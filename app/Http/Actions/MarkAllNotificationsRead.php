<?php

namespace App\Http\Actions;

use App\Modules\Notification\Services\NotificationService;
use Illuminate\Http\Request;

class MarkAllNotificationsRead
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $this->notificationService->markAllAsRead($gameId);

        return redirect()->route('show-game', $gameId);
    }
}
