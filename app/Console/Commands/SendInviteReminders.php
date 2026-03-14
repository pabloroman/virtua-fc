<?php

namespace App\Console\Commands;

use App\Jobs\SendBetaInviteReminder;
use App\Models\InviteCode;
use Illuminate\Console\Command;

class SendInviteReminders extends Command
{
    protected $signature = 'beta:send-invite-reminders';

    protected $description = 'Send reminder emails to invited users who have not registered after 3 days';

    public function handle(): int
    {
        if (! config('beta.enabled')) {
            $this->info('Beta mode is disabled, skipping.');

            return Command::SUCCESS;
        }

        $inviteCodes = InviteCode::where('invite_sent', true)
            ->where('times_used', 0)
            ->where('invite_sent_at', '<=', now()->subDays(3))
            ->whereNull('reminder_sent_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        if ($inviteCodes->isEmpty()) {
            $this->info('No invite codes eligible for reminder.');

            return Command::SUCCESS;
        }

        foreach ($inviteCodes as $inviteCode) {
            SendBetaInviteReminder::dispatch($inviteCode);
            sleep(3);
            $this->line("  Dispatched reminder for: {$inviteCode->email}");
        }

        $this->info("Dispatched {$inviteCodes->count()} reminder(s).");

        return Command::SUCCESS;
    }
}
