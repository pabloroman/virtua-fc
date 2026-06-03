<?php

namespace App\Http\Actions;

use App\Modules\Notification\Services\NotificationService;
use Illuminate\Http\Request;

class AcknowledgeCriticalAlerts
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        // The popup shows one alert at a time and posts its id, so dismiss only
        // that alert; the next pending critical surfaces on the following load.
        $this->notificationService->markCriticalAsRead($gameId, $request->input('notification_id'));

        // Return to the page the user dismissed the popup from (falls back to
        // the dashboard) so the alert simply clears in place.
        return redirect()->back(fallback: route('show-game', $gameId));
    }
}
