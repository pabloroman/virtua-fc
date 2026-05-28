<?php

namespace App\Modules\LiveMatch\Events;

use App\Models\LiveMatchSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveMatchBotTakeoverBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public LiveMatchSession $session,
        public int $userId,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("live-match.{$this->session->id}");
    }

    public function broadcastAs(): string
    {
        return 'match.bot_takeover';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'user_id' => $this->userId,
            'side' => $this->session->isHost($this->userId) ? 'home' : 'away',
        ];
    }
}
