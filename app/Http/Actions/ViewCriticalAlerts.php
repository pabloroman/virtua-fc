<?php

namespace App\Http\Actions;

use App\Models\GameNotification;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Http\Request;

class ViewCriticalAlerts
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $type = $request->input('type');

        // Every critical in the group shares a type, so any one of them resolves
        // the same destination — use the most recent as the navigation reference.
        $alert = GameNotification::where('game_id', $gameId)
            ->unread()
            ->where('priority', GameNotification::PRIORITY_CRITICAL)
            ->where('type', $type)
            ->orderByDesc('game_date')
            ->first();

        // Clear the whole group, not just one: otherwise the rest would re-pop on
        // the destination page. The underlying items (offers, competition) persist
        // independently, so marking the notifications read on navigate is safe.
        $this->notificationService->markCriticalAsRead($gameId, $type);

        if (! $alert) {
            return redirect()->route('show-game', $gameId);
        }

        return redirect()->route(
            $alert->getNavigationRoute(),
            $alert->getNavigationParams($gameId),
        );
    }
}
