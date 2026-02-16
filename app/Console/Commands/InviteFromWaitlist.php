<?php

namespace App\Console\Commands;

use App\Mail\BetaInvite;
use App\Models\InviteCode;
use App\Models\WaitlistEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InviteFromWaitlist extends Command
{
    protected $signature = 'beta:invite-waitlist
                            {--email= : Invite a specific email from the waitlist}
                            {--count= : Number of oldest waitlist entries to invite}
                            {--dry-run : Show what would be sent without actually sending}
                            {--expires= : Expiration date for invite codes (Y-m-d)}';

    protected $description = 'Generate and send invite codes to waitlisted emails (by email or batch count)';

    public function handle(): int
    {
        if (! config('beta.enabled')) {
            $this->info('Beta mode is disabled, skipping.');

            return self::SUCCESS;
        }

        $email = $this->option('email');
        $count = $this->option('count');

        if (! $email && ! $count) {
            $this->error('Provide --email or --count.');

            return self::FAILURE;
        }

        if ($email) {
            return $this->inviteByEmail(strtolower($email));
        }

        return $this->inviteByCount((int) $count);
    }

    private function inviteByEmail(string $email): int
    {
        $entry = WaitlistEntry::where('email', $email)->first();

        if (! $entry) {
            $this->error("Email {$email} is not on the waitlist.");

            return self::FAILURE;
        }

        if ($entry->inviteCode()->exists()) {
            $this->error("Email {$email} has already been invited.");

            return self::FAILURE;
        }

        $this->sendInvite($entry);
        $this->info("Invited: {$entry->name} <{$email}>");

        return self::SUCCESS;
    }

    private function inviteByCount(int $count): int
    {
        if ($count <= 0) {
            $this->error('Count must be a positive number.');

            return self::FAILURE;
        }

        $entries = WaitlistEntry::whereDoesntHave('inviteCode')
            ->orderBy('created_at')
            ->limit($count)
            ->get();

        if ($entries->isEmpty()) {
            $this->info('No pending waitlist entries to invite.');

            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');
        $sent = 0;

        foreach ($entries as $entry) {
            if ($dryRun) {
                $this->line("  [dry-run] Would invite: {$entry->name} <{$entry->email}>");
                $sent++;

                continue;
            }

            $this->sendInvite($entry);
            $this->info("  Invited: {$entry->name} <{$entry->email}>");
            $sent++;

            if ($sent < $entries->count()) {
                sleep(1);
            }
        }

        $this->newLine();
        $action = $dryRun ? 'Would invite' : 'Invited';
        $this->info("{$action}: {$sent} of {$entries->count()} waitlist entries.");

        return self::SUCCESS;
    }

    private function sendInvite(WaitlistEntry $entry): void
    {
        $invite = InviteCode::create([
            'code' => $this->generateCode(),
            'email' => strtolower($entry->email),
            'max_uses' => 1,
            'expires_at' => $this->option('expires'),
        ]);

        Mail::to($entry->email)->send(new BetaInvite($invite));

        $invite->update([
            'invite_sent' => true,
            'invite_sent_at' => now(),
        ]);
    }

    private function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (InviteCode::where('code', $code)->exists());

        return $code;
    }
}
