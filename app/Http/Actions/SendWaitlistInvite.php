<?php

namespace App\Http\Actions;

use App\Mail\BetaInvite;
use App\Models\InviteCode;
use App\Models\WaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendWaitlistInvite
{
    public function __invoke(Request $request, WaitlistEntry $waitlistEntry)
    {
        if (! config('beta.enabled')) {
            return back()->with('error', __('admin.waitlist_beta_disabled'));
        }

        if ($waitlistEntry->inviteCode()->exists()) {
            return back()->with('error', __('admin.waitlist_already_invited'));
        }

        $invite = InviteCode::create([
            'code' => $this->generateCode(),
            'email' => strtolower($waitlistEntry->email),
            'max_uses' => 1,
        ]);

        Mail::to($waitlistEntry->email)->send(new BetaInvite($invite));

        $invite->update([
            'invite_sent' => true,
            'invite_sent_at' => now(),
        ]);

        return back()->with('success', __('admin.waitlist_invite_sent'));
    }

    private function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (InviteCode::where('code', $code)->exists());

        return $code;
    }
}
