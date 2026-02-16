<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\InviteCode|null $inviteCode
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WaitlistEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WaitlistEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WaitlistEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WaitlistEntry whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WaitlistEntry whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WaitlistEntry whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WaitlistEntry whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WaitlistEntry whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class WaitlistEntry extends Model
{
    protected $table = 'waitlist';

    protected $fillable = ['name', 'email'];

    public function inviteCode(): HasOne
    {
        return $this->hasOne(InviteCode::class, 'email', 'email');
    }
}
