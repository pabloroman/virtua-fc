<?php

namespace App\Listeners;

use App\Models\DeviceSession;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;

class LogDeviceSession
{
    public function __construct(
        private Request $request,
    ) {}

    public function handle(Login $event): void
    {
        $agent = new Agent();
        $agent->setUserAgent($this->request->userAgent());

        if ($agent->isTablet()) {
            $deviceType = 'tablet';
        } elseif ($agent->isMobile()) {
            $deviceType = 'mobile';
        } else {
            $deviceType = 'desktop';
        }

        DeviceSession::create([
            'user_id' => $event->user->getAuthIdentifier(),
            'device_type' => $deviceType,
            'browser' => $agent->browser() ?: null,
            'os' => $agent->platform() ?: null,
            'logged_in_at' => now(),
        ]);
    }
}
