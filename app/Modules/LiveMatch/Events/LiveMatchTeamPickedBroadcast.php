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
            'host_team_id' => $this->session->host_team_id,
            'guest_team_id' => $this->session->guest_team_id,
            'both_picked' => $this->session->bothTeamsPicked(),
        ];
    }
}
