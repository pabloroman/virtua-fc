<?php

namespace App\Jobs;

use App\Models\WaitlistEntry;
use App\Services\BetaInviteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAutomaticWaitlistInvite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function __construct(
        private readonly string $waitlistEntryId,
    ) {
        $this->onQueue('mail');
    }

    public function handle(BetaInviteService $inviteService): void
    {
        if (! config('beta.enabled')) {
            return;
        }

        $entry = WaitlistEntry::find($this->waitlistEntryId);

        if (! $entry) {
            return;
        }

        if ($inviteService->hasAlreadyBeenInvited($entry->email)) {
            return;
        }

        $inviteService->invite($entry);
    }
}
