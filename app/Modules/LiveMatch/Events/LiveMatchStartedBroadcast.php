<?php

namespace App\Modules\LiveMatch\Events;

use App\Models\LiveMatchSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveMatchStartedBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public LiveMatchSession $session) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("live-match.{$this->session->id}");
    }

    public function broadcastAs(): string
    {
        return 'match.started';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'phase' => $this->session->phase->value,
            'current_minute' => $this->session->current_minute,
        ];
    }
}
