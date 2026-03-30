<?php

namespace App\Console\Commands;

use App\Jobs\ProcessBulkWaitlistInvites;
use App\Models\WaitlistEntry;
use App\Services\BetaInviteService;
use Illuminate\Console\Command;

class InviteFromWaitlist extends Command
{
    protected $signature = 'beta:invite-waitlist
                            {--email= : Invite a specific email from the waitlist}
                            {--count= : Number of random waitlist entries to invite}
                            {--dry-run : Show what would be sent without actually sending}
                            {--expires= : Expiration date for invite codes (Y-m-d)}';

    protected $description = 'Generate and send invite codes to waitlisted emails (by email or batch count)';

    public function __construct(
        private readonly BetaInviteService $inviteService,
    ) {
        parent::__construct();
    }

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

        if ($this->inviteService->hasAlreadyBeenInvited($email)) {
            $this->error("Email {$email} has already been invited.");

            return self::FAILURE;
        }

        $this->inviteService->invite($entry, $this->option('expires'));
        $this->info("Invited: {$entry->name} <{$email}>");

        return self::SUCCESS;
    }

    private function inviteByCount(int $count): int
    {
        if ($count <= 0) {
            $this->error('Count must be a positive number.');

            return self::FAILURE;
        }

        $pending = WaitlistEntry::whereDoesntHave('inviteCode')->earlyAdopter()->count();

        if ($pending === 0) {
            $this->info('No pending waitlist entries to invite.');

            return self::SUCCESS;
        }

        $count = min($count, $pending);

        if ($this->option('dry-run')) {
            $entries = WaitlistEntry::whereDoesntHave('inviteCode')
                ->earlyAdopter()
                ->inRandomOrder()
                ->limit($count)
                ->get();

            foreach ($entries as $entry) {
                $this->line("  [dry-run] Would invite: {$entry->name} <{$entry->email}>");
            }

            $this->newLine();
            $this->info("Would invite: {$entries->count()} of {$pending} waitlist entries.");

            return self::SUCCESS;
        }

        ProcessBulkWaitlistInvites::dispatch($count);
        $this->info("Dispatched job to invite {$count} waitlist entries.");

        return self::SUCCESS;
    }
}
