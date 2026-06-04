<?php

use App\Models\LiveMatchSession;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('live-match.{sessionId}', function (User $user, string $sessionId) {
    $session = LiveMatchSession::find($sessionId);
    if ($session === null) {
        return false;
    }

    if (! $session->isParticipant($user->id)) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'role' => $session->isHost($user->id) ? 'host' : 'guest',
    ];
});
