<?php

namespace App\Console\Commands;

use App\Mail\BetaInvite;
use App\Models\InviteCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendBetaInvites extends Command
{
    protected $signature = 'beta:send-invites
                            {--limit=0 : Max number of invites to send (0 = all pending)}
                            {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Send beta invitation emails to waitlist users with unsent codes';

    public function handle(): int
    {
        $query = InviteCode::whereNotNull('email')
            ->where('invite_sent', false)
            ->where('times_used', 0);

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $invites = $query->get();

        if ($invites->isEmpty()) {
            $this->info('No pending invites to send.');

            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');
        $sent = 0;

        foreach ($invites as $invite) {
            $registerUrl = url('/register?invite='.$invite->code);

            if ($dryRun) {
                $this->line("  [dry-run] Would send to: {$invite->email} (code: {$invite->code})");
                $this->line("            URL: {$registerUrl}");
            } else {
                Mail::to($invite->email)->send(new BetaInvite($invite));

                // Wait a second to avoid rate limiting
                sleep(1);

                $invite->update([
                    'invite_sent' => true,
                    'invite_sent_at' => now(),
                ]);

                $this->info("  Sent: {$invite->email}");
            }

            $sent++;
        }

        $this->newLine();
        $action = $dryRun ? 'Would send' : 'Sent';
        $this->info("{$action}: {$sent} invite(s).");

        return self::SUCCESS;
    }
}
