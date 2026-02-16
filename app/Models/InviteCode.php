<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string|null $email
 * @property int $max_uses
 * @property int $times_used
 * @property bool $invite_sent
 * @property \Illuminate\Support\Carbon|null $invite_sent_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereInviteSent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereInviteSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereMaxUses($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereTimesUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteCode whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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

    public static function findByCode(?string $code): ?self
    {
        if ($code === null) {
            return null;
        }

        return static::where('code', $code)->first();
    }
}
