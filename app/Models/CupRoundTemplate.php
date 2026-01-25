<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CupRoundTemplate extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'round_number' => 'integer',
        'first_leg_date' => 'date',
        'second_leg_date' => 'date',
        'teams_entering' => 'integer',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function isTwoLegged(): bool
    {
        return $this->type === 'two_leg';
    }

    public function isOneLeg(): bool
    {
        return $this->type === 'one_leg';
    }
}
