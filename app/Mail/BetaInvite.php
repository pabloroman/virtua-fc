<?php

namespace App\Mail;

use App\Models\InviteCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BetaInvite extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public InviteCode $inviteCode,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('beta.email_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.beta-invite',
            with: [
                'registerUrl' => url('/register?invite='.$this->inviteCode->code),
                'code' => $this->inviteCode->code,
            ],
        );
    }
}
