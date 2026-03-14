<?php

namespace App\Modules\Season\Services;

use App\Models\ActivationEvent;

class ActivationTracker
{
    public function record(int $userId, string $event, ?string $gameId = null): void
    {
        ActivationEvent::insertOrIgnore([
            'user_id' => $userId,
            'game_id' => $gameId,
            'event' => $event,
            'occurred_at' => now(),
        ]);
    }
}
