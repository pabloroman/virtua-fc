<?php

namespace App\Console\Commands;

use App\Jobs\SendBetaFeedbackRequest;
use App\Models\InviteCode;
use App\Models\User;
use Illuminate\Console\Command;

class SendBetaFeedbackRequests extends Command
{
    protected $signature = 'app:send-beta-feedback-requests';

    protected $description = 'Send feedback request emails to beta users whose invite was sent over 24 hours ago';

    public function handle(): int
    {
        if (! config('beta.enabled')) {
            $this->info('Beta mode is disabled, skipping.');

            return Command::SUCCESS;
        }

        $eligibleEmails = InviteCode::where('invite_sent', true)
            ->where('invite_sent_at', '<=', now()->subHours(24))
            ->pluck('email')
            ->map(fn ($email) => strtolower($email));

        $users = User::whereNull('feedback_requested_at')
            ->whereIn('email', $eligibleEmails)
            ->get();

        if ($users->isEmpty()) {
            $this->info('No users eligible for feedback request.');

            return Command::SUCCESS;
        }

        foreach ($users as $user) {
            SendBetaFeedbackRequest::dispatch($user);
            sleep(3);
            $this->line("  Dispatched feedback request for: {$user->email}");
        }

        $this->info("Dispatched {$users->count()} feedback request(s).");

        return Command::SUCCESS;
    }
}
