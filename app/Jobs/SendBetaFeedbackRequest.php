<?php

namespace App\Jobs;

use App\Mail\BetaFeedbackRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBetaFeedbackRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user,
    ) {}

    public function handle(): void
    {
        if (! config('beta.enabled')) {
            return;
        }

        $this->user->refresh();

        if ($this->user->feedback_requested_at) {
            return;
        }

        Mail::to($this->user->email)->send(new BetaFeedbackRequest($this->user));

        $this->user->update(['feedback_requested_at' => now()]);
    }
}
