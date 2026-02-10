<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InviteCode extends Model
{
    protected $fillable = [
        'code',
        'email',
        'max_uses',
        'times_used',
        'invite_sent',
        'invite_sent_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'invite_sent' => 'boolean',
            'invite_sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'max_uses' => 'integer',
            'times_used' => 'integer',
        ];
    }

    public function isValid(): bool
    {
        if ($this->times_used >= $this->max_uses) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isValidForEmail(string $email): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        if ($this->email && strtolower($this->email) !== strtolower($email)) {
            return false;
        }

        return true;
    }

    public function consume(): void
    {
        $this->increment('times_used');
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }
}
