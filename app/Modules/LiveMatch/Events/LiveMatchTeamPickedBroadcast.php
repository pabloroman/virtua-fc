<?php

namespace App\Modules\LiveMatch\Events;

use App\Models\LiveMatchSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveMatchTeamPickedBroadcast implements ShouldBroadcastNow
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
        return 'match.team_picked';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'host_iso_code' => $this->session->host_iso_code,
            'guest_iso_code' => $this->session->guest_iso_code,
            'both_picked' => $this->session->bothTeamsPicked(),
        ];
    }
}
