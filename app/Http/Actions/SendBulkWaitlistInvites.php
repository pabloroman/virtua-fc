<?php

namespace App\Http\Actions;

use App\Jobs\ProcessBulkWaitlistInvites;
use App\Models\WaitlistEntry;
use Illuminate\Http\Request;

class SendBulkWaitlistInvites
{
    public function __invoke(Request $request)
    {
        if (! config('beta.enabled')) {
            return back()->with('error', __('admin.waitlist_beta_disabled'));
        }

        $pending = WaitlistEntry::whereDoesntHave('inviteCode')->earlyAdopter()->count();

        if ($pending === 0) {
            return back()->with('error', __('admin.waitlist_no_pending'));
        }

        $count = min(50, $pending);

        ProcessBulkWaitlistInvites::dispatch($count);

        return back()->with('success', __('admin.waitlist_bulk_invite_started', ['count' => $count]));
    }
}
