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
        // The popup groups all pending criticals of one type and posts that type,
        // so dismiss clears the whole group at once; criticals of other types
        // surface as their own group on the following load.
        $this->notificationService->markCriticalAsRead($gameId, $request->input('type'));

        // Return to the page the user dismissed the popup from (falls back to
        // the dashboard) so the alert simply clears in place.
        return redirect()->back(fallback: route('show-game', $gameId));
    }
}
