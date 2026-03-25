<?php

namespace App\Services;

use App\Mail\BetaInvite;
use App\Models\InviteCode;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BetaInviteService
{
    public function invite(WaitlistEntry $entry, ?string $expiresAt = null): InviteCode
    {
        $invite = InviteCode::create([
            'code' => $this->generateCode(),
            'email' => strtolower($entry->email),
            'max_uses' => 1,
            'expires_at' => $expiresAt,
        ]);

        Mail::to($entry->email)->send(new BetaInvite($invite));

        $invite->update([
            'invite_sent' => true,
            'invite_sent_at' => now(),
        ]);

        return $invite;
    }

    public function hasAlreadyBeenInvited(string $email): bool
    {
        return InviteCode::where('email', strtolower($email))->exists();
    }

    private function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (InviteCode::where('code', $code)->exists());

        return $code;
    }
}
