<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class ActivateAccount extends Notification
{
    public function __construct(
        #[\SensitiveParameter] public string $token,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject(Lang::get('auth.activation_email_subject'))
            ->line(Lang::get('auth.activation_email_greeting', ['name' => $notifiable->name]))
            ->line(Lang::get('auth.activation_email_body'))
            ->action(Lang::get('auth.Activate Account'), $url)
            ->line(Lang::get('auth.activation_email_expiry', [
                'count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
            ]));
    }
}
