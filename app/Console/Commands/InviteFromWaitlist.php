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
                            {count : Number of waitlist entries to invite}
                            {--dry-run : Show what would be sent without actually sending}
                            {--expires= : Expiration date for invite codes (Y-m-d)}';

    protected $description = 'Generate and send invite codes to the oldest waitlist entries';

    public function handle(): int
    {
        $count = (int) $this->argument('count');

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
        $expiresAt = $this->option('expires');
        $sent = 0;

        foreach ($entries as $entry) {
            $code = $this->generateCode();
            $registerUrl = url('/register?invite='.$code);

            if ($dryRun) {
                $this->line("  [dry-run] Would invite: {$entry->name} <{$entry->email}>");
                $this->line("            Code: {$code} | URL: {$registerUrl}");
                $sent++;

                continue;
            }

            $invite = InviteCode::create([
                'code' => $code,
                'email' => strtolower($entry->email),
                'max_uses' => 1,
                'expires_at' => $expiresAt,
            ]);

            Mail::to($entry->email)->send(new BetaInvite($invite));

            $invite->update([
                'invite_sent' => true,
                'invite_sent_at' => now(),
            ]);

            $this->info("  Invited: {$entry->name} <{$entry->email}> → {$invite->code}");
            $sent++;

            // Avoid rate limiting
            if ($sent < $entries->count()) {
                sleep(1);
            }
        }

        $this->newLine();
        $action = $dryRun ? 'Would invite' : 'Invited';
        $this->info("{$action}: {$sent} of {$entries->count()} waitlist entries.");

        return self::SUCCESS;
    }

    private function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (InviteCode::where('code', $code)->exists());

        return $code;
    }
}
