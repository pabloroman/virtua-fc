<?php

namespace App\Jobs;

use App\Mail\BetaInviteReminder;
use App\Models\InviteCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBetaInviteReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public InviteCode $inviteCode,
    ) {}

    public function handle(): void
    {
        if (! config('beta.enabled')) {
            return;
        }

        $this->inviteCode->refresh();

        if ($this->inviteCode->reminder_sent_at) {
            return;
        }

        if ($this->inviteCode->times_used > 0) {
            return;
        }

        Mail::to($this->inviteCode->email)->send(new BetaInviteReminder($this->inviteCode));

        $this->inviteCode->update(['reminder_sent_at' => now()]);
    }
}
