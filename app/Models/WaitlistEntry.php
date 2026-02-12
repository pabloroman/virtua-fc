<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WaitlistEntry extends Model
{
    protected $table = 'waitlist';

    protected $fillable = ['name', 'email'];

    public function inviteCode(): HasOne
    {
        return $this->hasOne(InviteCode::class, 'email', 'email');
    }
}
