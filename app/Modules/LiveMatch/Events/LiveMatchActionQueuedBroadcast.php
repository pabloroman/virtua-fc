<?php

namespace App\Modules\LiveMatch\Events;

use App\Models\LiveMatchAction;
use App\Models\LiveMatchSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveMatchActionQueuedBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public LiveMatchSession $session,
        public LiveMatchAction $action,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("live-match.{$this->session->id}");
    }

    public function broadcastAs(): string
    {
        return 'match.action_queued';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'side' => $this->action->side->value,
            'action_type' => $this->action->action_type->value,
            // Deliberately do not leak full payload — opponent only sees that
            // something is being prepared, not what.
        ];
    }
}
